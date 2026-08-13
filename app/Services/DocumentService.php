<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentService
{
    public function disk()
    {
        return Storage::disk(config('documents.disk'));
    }

    /** Direct children of $parentPath (not the whole subtree) as SVAR IEntity arrays. */
    public function listChildren(int $ownerId, string $parentPath): array
    {
        return Document::query()
            ->where('owner_id', $ownerId)
            ->where('parent_path', $this->normalize($parentPath))
            ->orderBy('type', 'desc') // folders first
            ->orderBy('name')
            ->get()
            ->map(fn (Document $d) => $d->toEntity())
            ->all();
    }

    public function info(int $ownerId): array
    {
        $used = (int) Document::where('owner_id', $ownerId)->where('type', 'file')->sum('size');

        return [
            'used'  => $used,
            'total' => config('documents.quota_mb_per_owner') * 1024 * 1024,
        ];
    }

    public function createFolder(int $ownerId, string $parentPath, string $name, int $actorId): Document
    {
        $path = $this->uniquePath($ownerId, Document::joinPath($this->normalize($parentPath), $this->sanitizeName($name)));

        return Document::create([
            'owner_id'    => $ownerId,
            'created_by'  => $actorId,
            'path'        => $path,
            'name'        => Document::nameOf($path),
            'parent_path' => Document::parentPathOf($path),
            'type'        => 'folder',
        ]);
    }

    /** "Add new file" from the menu: an empty placeholder file, no upload involved. */
    public function createEmptyFile(int $ownerId, string $parentPath, string $name, int $actorId): Document
    {
        $path        = $this->uniquePath($ownerId, Document::joinPath($this->normalize($parentPath), $this->sanitizeName($name)));
        $storagePath = $this->newStoragePath($ownerId, $path);

        $this->disk()->put($storagePath, '');

        return Document::create([
            'owner_id'     => $ownerId,
            'created_by'   => $actorId,
            'path'         => $path,
            'name'         => Document::nameOf($path),
            'parent_path'  => Document::parentPathOf($path),
            'type'         => 'file',
            'storage_path' => $storagePath,
            'mime_type'    => 'application/octet-stream',
            'size'         => 0,
        ]);
    }

    public function upload(int $ownerId, string $parentPath, UploadedFile $file, ?string $name, int $actorId): Document
    {
        $maxBytes = config('documents.max_upload_mb') * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            throw new RuntimeException('File exceeds the maximum upload size of '.config('documents.max_upload_mb').' MB.');
        }

        $info = $this->info($ownerId);
        if ($info['used'] + $file->getSize() > $info['total']) {
            throw new RuntimeException('This would exceed the storage quota.');
        }

        $desiredName = $this->sanitizeName($name ?: $file->getClientOriginalName());
        $path        = $this->uniquePath($ownerId, Document::joinPath($this->normalize($parentPath), $desiredName));
        $storagePath = $this->newStoragePath($ownerId, $path);

        $this->disk()->putFileAs(dirname($storagePath), $file, basename($storagePath));

        return Document::create([
            'owner_id'     => $ownerId,
            'created_by'   => $actorId,
            'path'         => $path,
            'name'         => Document::nameOf($path),
            'parent_path'  => Document::parentPathOf($path),
            'type'         => 'file',
            'storage_path' => $storagePath,
            'mime_type'    => $file->getMimeType() ?: 'application/octet-stream',
            'size'         => $file->getSize(),
        ]);
    }

    public function rename(int $ownerId, string $path, string $newName): Document
    {
        $doc = $this->findOrFail($ownerId, $path);

        $newPath = $this->uniquePath(
            $ownerId,
            Document::joinPath(Document::parentPathOf($doc->path), $this->sanitizeName($newName)),
            excludeId: $doc->id,
        );

        return DB::transaction(function () use ($doc, $newPath) {
            $this->relocateSubtree($doc, $newPath);

            return $doc->refresh();
        });
    }

    /** @return array<string,string> old path => new path, for every moved node (including descendants) */
    public function move(int $ownerId, array $paths, string $targetPath): array
    {
        $target = $this->normalize($targetPath);
        $map    = [];

        DB::transaction(function () use ($ownerId, $paths, $target, &$map) {
            foreach ($paths as $path) {
                $doc = $this->findOrFail($ownerId, $path);

                if ($target === $doc->path || str_starts_with($target.'/', $doc->path.'/')) {
                    throw new RuntimeException('Cannot move a folder into itself.');
                }

                $newPath = $this->uniquePath($ownerId, Document::joinPath($target, $doc->name), excludeId: $doc->id);
                $map     = array_merge($map, $this->relocateSubtree($doc, $newPath));
            }
        });

        return $map;
    }

    /** @return array<string,string> old path => new path, for every copied node (including descendants) */
    public function copy(int $ownerId, array $paths, string $targetPath, int $actorId): array
    {
        $target = $this->normalize($targetPath);
        $map    = [];

        DB::transaction(function () use ($ownerId, $paths, $target, $actorId, &$map) {
            foreach ($paths as $path) {
                $doc = $this->findOrFail($ownerId, $path);
                $map = array_merge($map, $this->copySubtree($doc, $target, $ownerId, $actorId));
            }
        });

        return $map;
    }

    public function delete(int $ownerId, array $paths): void
    {
        DB::transaction(function () use ($ownerId, $paths) {
            foreach ($paths as $path) {
                $doc = Document::where('owner_id', $ownerId)->where('path', $this->normalize($path))->first();
                if (! $doc) {
                    continue;
                }

                $subtree = $this->subtreeOf($doc);
                foreach ($subtree as $node) {
                    if ($node->type === 'file' && $node->storage_path) {
                        $this->disk()->delete($node->storage_path);
                    }
                    $node->delete();
                }
            }
        });
    }

    public function findOrFail(int $ownerId, string $path): Document
    {
        return Document::where('owner_id', $ownerId)->where('path', $this->normalize($path))->firstOrFail();
    }

    // ── internals ──────────────────────────────────────────────────────────

    private function normalize(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path === Document::ROOT) {
            return Document::ROOT;
        }

        return '/'.trim($path, '/');
    }

    private function sanitizeName(string $name): string
    {
        $name = trim(str_replace(['/', "\0"], '', $name));

        return $name === '' ? 'Untitled' : $name;
    }

    /** Appends " (1)", " (2)", ... before the extension until the path is free for this owner. */
    private function uniquePath(int $ownerId, string $path, ?int $excludeId = null): string
    {
        $exists = fn (string $p) => Document::where('owner_id', $ownerId)
            ->where('path', $p)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        if (! $exists($path)) {
            return $path;
        }

        $parent = Document::parentPathOf($path);
        $name   = Document::nameOf($path);
        $ext    = '';
        $base   = $name;

        if (($dot = strrpos($name, '.')) !== false && $dot > 0) {
            $base = substr($name, 0, $dot);
            $ext  = substr($name, $dot);
        }

        for ($i = 1; $i < 1000; $i++) {
            $candidate = Document::joinPath($parent, "{$base} ({$i}){$ext}");
            if (! $exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Could not find a free name.');
    }

    private function newStoragePath(int $ownerId, string $virtualPath): string
    {
        $ext = pathinfo($virtualPath, PATHINFO_EXTENSION);

        return "{$ownerId}/".Str::uuid().($ext ? ".{$ext}" : '');
    }

    /** @return Document[] the node itself plus every descendant, parents before children */
    private function subtreeOf(Document $doc): array
    {
        if ($doc->type !== 'folder') {
            return [$doc];
        }

        $descendants = Document::where('owner_id', $doc->owner_id)
            ->where('path', 'like', $doc->path.'/%')
            ->get();

        return [$doc, ...$descendants];
    }

    /** Rewrites $doc's path (and every descendant's) to live under $newPath. Returns old=>new map. */
    private function relocateSubtree(Document $doc, string $newPath): array
    {
        $oldPath = $doc->path;
        $map     = [$oldPath => $newPath];

        $descendants = $doc->type === 'folder'
            ? Document::where('owner_id', $doc->owner_id)->where('path', 'like', $oldPath.'/%')->get()
            : collect();

        foreach ($descendants as $descendant) {
            $newDescendantPath = $newPath.substr($descendant->path, strlen($oldPath));
            $map[$descendant->path] = $newDescendantPath;
            $descendant->update([
                'path'        => $newDescendantPath,
                'parent_path' => Document::parentPathOf($newDescendantPath),
            ]);
        }

        $doc->update([
            'path'        => $newPath,
            'name'        => Document::nameOf($newPath),
            'parent_path' => Document::parentPathOf($newPath),
        ]);

        return $map;
    }

    /** Physically duplicates $doc (and, if a folder, its whole subtree) under $targetParentPath. */
    private function copySubtree(Document $doc, string $targetParentPath, int $ownerId, int $actorId): array
    {
        $newPath = $this->uniquePath($ownerId, Document::joinPath($targetParentPath, $doc->name));
        $map     = [$doc->path => $newPath];

        $newStoragePath = null;
        if ($doc->type === 'file' && $doc->storage_path) {
            $newStoragePath = $this->newStoragePath($ownerId, $newPath);
            $this->disk()->copy($doc->storage_path, $newStoragePath);
        }

        $copy = Document::create([
            'owner_id'     => $ownerId,
            'created_by'   => $actorId,
            'path'         => $newPath,
            'name'         => Document::nameOf($newPath),
            'parent_path'  => Document::parentPathOf($newPath),
            'type'         => $doc->type,
            'storage_path' => $newStoragePath,
            'mime_type'    => $doc->mime_type,
            'size'         => $doc->size,
        ]);

        if ($doc->type === 'folder') {
            $children = Document::where('owner_id', $doc->owner_id)->where('parent_path', $doc->path)->get();
            foreach ($children as $child) {
                $map = array_merge($map, $this->copySubtree($child, $copy->path, $ownerId, $actorId));
            }
        }

        return $map;
    }
}
