<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('precedents', function (Blueprint $table) {
            $table->string('output_title_template')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('precedents', function (Blueprint $table) {
            $table->dropColumn('output_title_template');
        });
    }
};
