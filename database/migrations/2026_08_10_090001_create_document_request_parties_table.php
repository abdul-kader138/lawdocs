<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_request_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained('document_requests')->cascadeOnDelete();
            // Matches a key in the owning Precedent's party_groups config (e.g.
            // "beneficiaries") — not a DB-enforced FK since party_groups is JSON.
            $table->string('group_key');
            // Stable order within the group — drives REPEAT emission order and
            // "1./2./3." numbering later; also what the Filament Repeater UI
            // reorders against.
            $table->unsignedInteger('position')->default(0);
            // The group's declared fields for this row (name, dob, relationship,
            // share, gender, per_stirpes, ...) — schema lives on Precedent::party_groups,
            // not here, mirroring the existing questionnaire_fields/answers split.
            $table->json('data');
            // Self-referential "specific substitute" pointer (e.g. an alternate
            // executor pointing at another row in the same request). Only
            // populated for groups with supports_substitute enabled.
            $table->foreignId('substitute_party_id')->nullable()
                ->constrained('document_request_parties')->nullOnDelete();
            $table->timestamps();

            $table->index('document_request_id');
            $table->index(['document_request_id', 'group_key', 'position'], 'document_request_parties_group_position_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_request_parties');
    }
};
