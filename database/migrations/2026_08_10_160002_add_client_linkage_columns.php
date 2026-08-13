<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('precedents', function (Blueprint $table) {
            // Admin-declared: which questionnaire_fields entry corresponds to
            // which Client attribute (name/dob/street/...) — lets the wizard
            // prefill answers.* from a selected Client without guessing at
            // field-name conventions (which already differ: "testator_name"
            // for wills vs "principal_name" for POA/Enduring Guardianship).
            $table->json('client_field_map')->nullable()->after('party_groups');
        });

        Schema::table('document_requests', function (Blueprint $table) {
            // Audit/history link only — nullable and independent of whatever
            // answers.* prefill actually happened (a request can exist with
            // no client at all, exactly like today). Mirrors precedent_id's
            // nullOnDelete: deleting a Client must never take a generated
            // document's history down with it.
            $table->foreignId('client_id')->nullable()->after('precedent_id')
                ->constrained('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });

        Schema::table('precedents', function (Blueprint $table) {
            $table->dropColumn('client_field_map');
        });
    }
};
