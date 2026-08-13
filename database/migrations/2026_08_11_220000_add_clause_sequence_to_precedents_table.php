<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('precedents', function (Blueprint $table) {
            // Admin-authored: ordered list of {heading, kind, tag_or_key, condition}
            // entries controlling which sections this precedent's generated document
            // has, in what order, and whether each is shown — read instead of the
            // generator's hardcoded sequence when non-empty. See
            // Precedent::clauseSequenceConfig() and ClauseSequenceRenderer. Null/empty
            // (every existing precedent, by default) means "use the generator's
            // hardcoded sequence, unchanged."
            $table->json('clause_sequence')->nullable()->after('party_groups');
        });
    }

    public function down(): void
    {
        Schema::table('precedents', function (Blueprint $table) {
            $table->dropColumn('clause_sequence');
        });
    }
};
