<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precedent_test_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('precedent_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('answers');
            $table->json('parties')->nullable();
            $table->string('expected_title')->nullable();
            $table->json('expected_includes')->nullable();
            $table->json('expected_excludes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['precedent_id', 'name']);
        });

        Schema::create('precedent_qa_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('precedent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fingerprint', 64);
            $table->string('status');
            $table->json('issues');
            $table->json('scenario_results');
            $table->json('comparison');
            $table->json('snapshot');
            $table->timestamps();
            $table->index(['precedent_id', 'created_at']);
        });

        Schema::create('precedent_qa_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('precedent_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fingerprint', 64);
            $table->json('snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precedent_qa_baselines');
        Schema::dropIfExists('precedent_qa_runs');
        Schema::dropIfExists('precedent_test_scenarios');
    }
};
