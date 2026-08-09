<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('precedents', function (Blueprint $table) {
            // Despite the name, this stores a short REGISTERED KEY (e.g. 'will'),
            // never a raw class-string — see App\Services\Generators\GeneratorRegistry.
            // Kept nullable so existing rows don't break; GeneratorRegistry::resolve()
            // fails loudly at generation time if it's still null.
            $table->string('generator_class')->nullable()->after('category');

            // Surfaces malformed clause markers at UPLOAD time (next to the
            // extracted_text preview), not only when a staff member tries to
            // generate a document from a broken precedent.
            $table->text('clause_marker_error')->nullable()->after('extracted_text');
        });
    }

    public function down(): void
    {
        Schema::table('precedents', function (Blueprint $table) {
            $table->dropColumn(['generator_class', 'clause_marker_error']);
        });
    }
};
