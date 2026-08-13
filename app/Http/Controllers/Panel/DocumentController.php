<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Backend for the SVAR React File Manager (@svar-ui/filemanager-data-provider's
 * RestDataProvider). Route/response shapes intentionally mirror what that
 * package's compiled source expects — see RestDataProvider.getHandlers()/send().
 *
 * "$owner" is a path segment, not read from auth alone: it lets an admin browse
 * another user's drive. Non-admins are always forced back to their own id below,
 * regardless of what's in the URL.
 */
class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    private function resolveOwner(Request $request, int $owner): User
    {
        /** @var User $user */
        $user = $request->user();

        if ((int) $owner === $user->id) {
            return $user;
        }

        if (! $user->isAdmin()) {
            throw new HttpException(403, 'You can only manage your own documents.');
        }

        return User::findOrFail($owner);
    }

    public function users(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            throw new HttpException(403);
        }

        return response()->json(
            User::orderBy('name')->get(['id', 'name', 'email'])
        );
    }

    public function index(Request $request, int $owner): JsonResponse
    {
        $ownerUser = $this->resolveOwner($request, $owner);

        return response()->json($this->documents->listChildren($ownerUser->id, Document::ROOT));
    }

    public function children(Request $request, int $owner, string $path): JsonResponse
    {
        $ownerUser = $this->resolveOwner($request, $owner);

        return response()->json($this->documents->listChildren($ownerUser->id, '/'.$path));
    }

    public function info(Request $request, int $owner): JsonResponse
    {
        $ownerUser = $this->resolveOwner($request, $owner);

        return response()->json($this->documents->info($ownerUser->id));
    }

    public function create(Request $request, int $owner, string $parent): JsonResponse
    {
        $ownerUser = $this->resolveOwner($request, $owner);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:file,folder'],
        ]);

        try {
            $doc = $data['type'] === 'folder'
                ? $this->documents->createFolder($ownerUser->id, '/'.$parent, $data['name'], $request->user()->id)
                : $this->documents->createEmptyFile($ownerUser->id, '/'.$parent, $data['name'], $request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['result' => ['id' => $doc->path]]);
    }

    public function upload(Request $request, int $owner): JsonResponse
    {
        $ownerUser = $this->resolveOwner($request, $owner);

        $request->validate([
            'file' => ['required', 'file'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $parent = '/'.ltrim((string) $request->query('id', '/'), '/');

        try {
            $doc = $this->documents->upload(
                $ownerUser->id,
                $parent,
                $request->file('file'),
                $request->input('name'),
                $request->user()->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['result' => ['id' => $doc->path]]);
    }

    public function rename(Request $request, int $owner, string $id): JsonResponse
    {
        $ownerUser = $this->resolveOwner($request, $owner);

        $data = $request->validate([
            'operation' => ['required', 'in:rename'],
            'name'      => ['required', 'string', 'max:255'],
        ]);

        try {
            $doc = $this->documents->rename($ownerUser->id, '/'.$id, $data['name']);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['result' => ['id' => $doc->path]]);
    }

    /** PUT files (no id) — 'operation' distinguishes move vs copy per the RestDataProvider contract. */
    public function bulk(Request $request, int $owner): JsonResponse
    {
        $ownerUser = $this->resolveOwner($request, $owner);

        $data = $request->validate([
            'operation' => ['required', 'in:move,copy'],
            'ids'       => ['required', 'array', 'min:1'],
            'ids.*'     => ['string'],
            'target'    => ['required', 'string'],
        ]);

        try {
            $map = $data['operation'] === 'move'
                ? $this->documents->move($ownerUser->id, $data['ids'], $data['target'])
                : $this->documents->copy($ownerUser->id, $data['ids'], $data['target'], $request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // Response must line up positionally with the requested ids (top level only) —
        // the client remaps descendants itself from its already-loaded local tree.
        $result = array_map(fn (string $id) => ['id' => $map['/'.ltrim($id, '/')] ?? $id], $data['ids']);

        return response()->json(['result' => $result]);
    }

    public function delete(Request $request, int $owner): JsonResponse
    {
        $ownerUser = $this->resolveOwner($request, $owner);

        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
        ]);

        $this->documents->delete($ownerUser->id, $data['ids']);

        return response()->json(['result' => true]);
    }

    public function download(Request $request, int $owner): StreamedResponse
    {
        $ownerUser = $this->resolveOwner($request, $owner);
        $path      = '/'.ltrim((string) $request->query('id', ''), '/');

        $doc = $this->documents->findOrFail($ownerUser->id, $path);

        if ($doc->type !== 'file' || ! $doc->storage_path) {
            throw new HttpException(404, 'Not a downloadable file.');
        }

        return $this->documents->disk()->download($doc->storage_path, $doc->name);
    }
}
