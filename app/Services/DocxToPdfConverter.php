<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Shells out to LibreOffice headless (`soffice --convert-to pdf`) rather
 * than PHPWord's own PDF writer or a pure-PHP renderer (dompdf/mpdf) —
 * those are known to render complex multilevel numbering (this app's core
 * feature, see App\Services\Clause\ClauseTemplateExpander) poorly or not at
 * all. Requires the `soffice` binary to be installed on the server; call
 * isAvailable() before offering PDF export in the UI.
 */
class DocxToPdfConverter
{
    private const TIMEOUT_SECONDS = 90;

    public function isAvailable(): bool
    {
        return Process::run('which soffice')->successful();
    }

    /**
     * Converts a .docx to PDF, returning the absolute path to the
     * resulting file inside its own throwaway directory — the caller owns
     * cleanup (see DownloadDocumentRequestPdfController).
     *
     * @throws \RuntimeException if soffice isn't installed, or conversion fails/times out
     */
    public function convert(string $absoluteDocxPath): string
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('PDF export is not available on this server — LibreOffice (soffice) is not installed.');
        }

        $workDir = storage_path('app/pdf-conversions/'.Str::uuid());
        // Each conversion gets its own LibreOffice user-profile directory:
        // concurrent conversions sharing one profile hit lock-file
        // contention and fail silently/intermittently otherwise.
        $profileDir = $workDir.'/profile';
        File::ensureDirectoryExists($profileDir);

        $result = Process::timeout(self::TIMEOUT_SECONDS)->run([
            'soffice', '--headless', '--norestore',
            '-env:UserInstallation=file://'.$profileDir,
            '--convert-to', 'pdf', '--outdir', $workDir,
            $absoluteDocxPath,
        ]);

        if ($result->failed()) {
            throw new \RuntimeException('PDF conversion failed: '.$result->errorOutput());
        }

        $pdfPath = $workDir.'/'.pathinfo($absoluteDocxPath, PATHINFO_FILENAME).'.pdf';

        if (! File::exists($pdfPath)) {
            throw new \RuntimeException('PDF conversion did not produce an output file.');
        }

        return $pdfPath;
    }
}
