<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadDocumentRequestController extends Controller
{
    public function __invoke(DocumentRequest $documentRequest)
    {
        Gate::authorize('download', $documentRequest);

        abort_unless(
            $documentRequest->status === 'completed' && $documentRequest->generated_docx_path,
            404
        );

        abort_unless(
            Storage::disk('local')->exists($documentRequest->generated_docx_path),
            404
        );

        $filename = Str::slug($documentRequest->generated_title ?: $documentRequest->precedent_title_snapshot) . '.docx';

        return Storage::disk('local')->download($documentRequest->generated_docx_path, $filename);
    }
}
