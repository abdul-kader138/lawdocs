<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            // Tracks the real-world signing process, not a live e-signature
            // provider integration (no such provider's credentials are
            // configured) — "sent" and "signed" are staff-recorded workflow
            // states, and signed_document_path is wherever the executed copy
            // (wet-signed and scanned, or externally e-signed) gets uploaded
            // back to. See App\Services\SignatureTrackingService for the
            // documented extension point if a real provider is wired up later.
            $table->timestamp('sent_for_signature_at')->nullable()->after('approved_by');
            $table->timestamp('signed_at')->nullable()->after('sent_for_signature_at');
            $table->string('signed_document_path')->nullable()->after('signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropColumn(['sent_for_signature_at', 'signed_at', 'signed_document_path']);
        });
    }
};
