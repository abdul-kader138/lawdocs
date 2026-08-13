<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $t) {
            $t->id();

            // Whose "drive" this entry lives in. Every query is scoped by this column;
            // non-admins are hard-locked to their own id, admins can switch.
            $t->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Virtual path, e.g. "/Reports/Q1.pdf". This is the literal "id" the SVAR
            // File Manager uses on the frontend — it derives parent/name by splitting
            // on "/", so the path IS the identity, not a display convenience. Capped at
            // 500 (not a bigger round number) so the owner_id+path unique index and the
            // owner_id+parent_path index both stay under InnoDB's 3072-byte max key
            // length under utf8mb4 (4 bytes/char) — plenty for realistic folder depth.
            $t->string('path', 500);
            $t->string('name');
            $t->string('parent_path', 500);
            $t->enum('type', ['file', 'folder']);

            // Physical storage is decoupled from the virtual path/name so renaming or
            // moving a file is a pure DB update — no filesystem move needed.
            $t->string('disk')->default('documents');
            $t->string('storage_path')->nullable();
            $t->string('mime_type')->nullable();
            $t->unsignedBigInteger('size')->default(0);

            $t->timestamps();

            $t->unique(['owner_id', 'path']);
            $t->index(['owner_id', 'parent_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
