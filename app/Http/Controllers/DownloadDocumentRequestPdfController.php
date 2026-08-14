<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Services\DocumentRequestPdfCache;
use App\Services\DocxToPdfConverter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadDocumentRequestPdfController extends Controller
{
    public function __invoke(DocumentRequest $documentRequest, DocxToPdfConverter $converter, DocumentRequestPdfCache $pdfCache)
    {
        // Same policy ability as the .docx download — download() mirrors
        // view() by design (see DocumentRequestPolicy), and PDF is just
        // another export format of the same underlying file.
        Gate::authorize('download', $documentRequest);

        abort_unless(
            $documentRequest->isReadyForDownload() && $documentRequest->generated_docx_path,
            404
        );

        abort_unless(
            Storage::disk('local')->exists($documentRequest->generated_docx_path),
            404
        );

        abort_unless($converter->isAvailable(), 503, 'PDF export is not available on this server.');

        $pdfPath = $pdfCache->resolve($documentRequest);

        $filename = Str::slug($documentRequest->generated_title ?: $documentRequest->precedent_title_snapshot).'.pdf';

        return response()->download($pdfPath, $filename);
    }
}
