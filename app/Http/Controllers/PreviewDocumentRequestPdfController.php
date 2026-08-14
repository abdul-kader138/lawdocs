<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Services\DocxToPdfConverter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PreviewDocumentRequestPdfController extends Controller
{
    public function __invoke(DocumentRequest $documentRequest, DocxToPdfConverter $converter)
    {
        // Deliberately 'view', not 'download': this exists so a reviewer can
        // read a completed draft BEFORE approving it. download() mirrors
        // view() today, but is conceptually tied to the approval-gated
        // download flow — if it's ever refactored to encode approval itself,
        // gating on it here would silently break the whole point of this
        // route.
        Gate::authorize('view', $documentRequest);

        abort_unless(
            $documentRequest->status === 'completed' && $documentRequest->generated_docx_path,
            404
        );

        abort_unless(
            Storage::disk('local')->exists($documentRequest->generated_docx_path),
            404
        );

        abort_unless($converter->isAvailable(), 503, 'PDF preview is not available on this server.');

        $pdfPath = $converter->convert(Storage::disk('local')->path($documentRequest->generated_docx_path));
        $workDir = dirname($pdfPath);

        $filename = Str::slug($documentRequest->generated_title ?: $documentRequest->precedent_title_snapshot).'.pdf';

        app()->terminating(fn () => File::deleteDirectory($workDir));

        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
