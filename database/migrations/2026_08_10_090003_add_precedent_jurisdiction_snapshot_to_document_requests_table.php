<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            // Mirrors precedent_title_snapshot exactly: survives the source
            // precedent being edited/deleted later, for audit purposes.
            $table->string('precedent_jurisdiction_snapshot')->nullable()->after('precedent_title_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropColumn('precedent_jurisdiction_snapshot');
        });
    }
};
