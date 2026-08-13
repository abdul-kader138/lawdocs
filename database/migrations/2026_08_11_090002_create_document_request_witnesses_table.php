<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_request_witnesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained('document_requests')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('occupation')->nullable();
            $table->timestamps();

            $table->index('document_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_request_witnesses');
    }
};
