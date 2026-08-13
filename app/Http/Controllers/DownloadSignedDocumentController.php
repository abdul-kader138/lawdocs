<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadSignedDocumentController extends Controller
{
    public function __invoke(DocumentRequest $documentRequest)
    {
        // Same policy ability as the drafted .docx/.pdf — a signed copy is
        // just another artifact of the same request, same trust boundary.
        Gate::authorize('download', $documentRequest);

        abort_unless($documentRequest->signed_document_path, 404);
        abort_unless(Storage::disk('local')->exists($documentRequest->signed_document_path), 404);

        $extension = pathinfo($documentRequest->signed_document_path, PATHINFO_EXTENSION) ?: 'pdf';
        $filename = Str::slug(($documentRequest->generated_title ?: $documentRequest->precedent_title_snapshot).'-signed').'.'.$extension;

        return Storage::disk('local')->download($documentRequest->signed_document_path, $filename);
    }
}
