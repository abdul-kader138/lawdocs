<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('precedents', function (Blueprint $table) {
            // Defaults true: every precedent this app currently ships is
            // demo/unreviewed legal content (see the generators' docblocks)
            // — the safe default is "needs a human sign-off before a
            // generated document is downloadable", not the other way round.
            $table->boolean('requires_review')->default(true)->after('is_active');
        });

        Schema::table('document_requests', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('generated_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });

        Schema::table('precedents', function (Blueprint $table) {
            $table->dropColumn('requires_review');
        });
    }
};
