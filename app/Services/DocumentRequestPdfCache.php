<?php

namespace App\Services;

use App\Models\DocumentRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Caches the PDF conversion of a DocumentRequest's generated .docx, so
 * repeated Preview/Download-PDF clicks reuse one LibreOffice conversion
 * instead of re-running soffice (a multi-second shell-out) on every click.
 * The .docx never changes after generation, so a cached PDF never goes
 * stale — there is no invalidation to handle.
 */
class DocumentRequestPdfCache
{
    public function __construct(private readonly DocxToPdfConverter $converter) {}

    /**
     * @return string absolute path to the cached (or freshly converted and
     *                now-cached) PDF on the local disk
     *
     * @throws \RuntimeException if soffice isn't installed, or conversion fails/times out
     */
    public function resolve(DocumentRequest $documentRequest): string
    {
        if ($documentRequest->generated_pdf_path
            && Storage::disk('local')->exists($documentRequest->generated_pdf_path)) {
            return Storage::disk('local')->path($documentRequest->generated_pdf_path);
        }

        $tempPdfPath = $this->converter->convert(
            Storage::disk('local')->path($documentRequest->generated_docx_path)
        );

        $relativePath = 'generated/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($relativePath, file_get_contents($tempPdfPath));
        File::deleteDirectory(dirname($tempPdfPath));

        $documentRequest->update(['generated_pdf_path' => $relativePath]);

        return Storage::disk('local')->path($relativePath);
    }
}
