<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('precedents', function (Blueprint $table) {
            // Admin-authored: repeatable party roles for this precedent (e.g.
            // Beneficiaries, Executors, Guardians) — schema-only, mirrors
            // questionnaire_fields. See Precedent::partyGroupsConfig().
            $table->json('party_groups')->nullable()->after('questionnaire_fields');

            // A precedent IS jurisdiction-specific (the .docx wording encodes
            // that state's Act requirements) — lives here, not on
            // document_requests, same reasoning as `category`. Default 'NSW'
            // backfills existing rows; whitelist enforced in
            // App\Support\AustralianJurisdictions, not a DB enum, so new
            // states are addable without a migration.
            $table->string('jurisdiction')->default('NSW')->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('precedents', function (Blueprint $table) {
            $table->dropColumn(['party_groups', 'jurisdiction']);
        });
    }
};
