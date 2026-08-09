<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            // Nullable + kept separately from the snapshot: a precedent can be
            // edited/deleted later without corrupting the historical record of
            // what was actually used to generate this document.
            $table->foreignId('precedent_id')->nullable()->constrained('precedents')->nullOnDelete();
            $table->string('precedent_title_snapshot');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('case_reference')->nullable();
            $table->json('answers');
            $table->string('status')->default('pending'); // pending|processing|completed|failed
            $table->text('error_message')->nullable();
            $table->string('generated_docx_path')->nullable();
            $table->string('generated_title')->nullable();
            $table->json('claude_raw_response')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('precedent_id');
            $table->index('requested_by');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};
