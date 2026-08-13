<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('precedents', function (Blueprint $table) {
            // Admin-authored per-precedent override of the global Document
            // Defaults font/heading style set in System Settings — {font_family,
            // font_size, heading_bold, heading_size_step}, all optional. Null/empty
            // (every existing precedent, by default) means "use the global
            // Setting/hardcoded default, unchanged." See Precedent::formattingConfig()
            // and DocxBuilder::build()'s $formattingOverrides param.
            $table->json('formatting')->nullable()->after('clause_sequence');
        });
    }

    public function down(): void
    {
        Schema::table('precedents', function (Blueprint $table) {
            $table->dropColumn('formatting');
        });
    }
};
