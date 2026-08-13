<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Null/absent = unrestricted (every existing user keeps today's
            // behavior — scoping is opt-in per user, never a silent
            // lockout). A populated array restricts which Precedent
            // *categories* (will/power_of_attorney/enduring_guardianship/...)
            // this user may manage — see PrecedentPolicy and
            // PrecedentResource::getEloquentQuery(). Deliberately only
            // affects precedent management, not document generation —
            // panel_user already can't manage precedents at all.
            $table->json('precedent_categories')->nullable()->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('precedent_categories');
        });
    }
};
