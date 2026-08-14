<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            // Cached PDF conversion of generated_docx_path — the .docx never
            // changes after generation, so once converted this is reused by
            // every subsequent Preview/Download-PDF click instead of
            // re-running LibreOffice each time. Null until first requested.
            $table->string('generated_pdf_path')->nullable()->after('generated_docx_path');
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropColumn('generated_pdf_path');
        });
    }
};
