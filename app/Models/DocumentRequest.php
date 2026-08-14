<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class DocumentRequest extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('document_request');
    }

    protected $fillable = [
        'precedent_id', 'precedent_title_snapshot', 'precedent_jurisdiction_snapshot',
        'client_id', 'requested_by', 'case_reference', 'answers', 'status', 'error_message',
        'generated_docx_path', 'generated_pdf_path', 'generated_title', 'generation_snapshot', 'generated_at',
        'approved_at', 'approved_by', 'sent_for_signature_at', 'signed_at', 'signed_document_path',
    ];

    protected $casts = [
        'answers' => 'array',
        'generation_snapshot' => 'array',
        'generated_at' => 'datetime',
        'approved_at' => 'datetime',
        'sent_for_signature_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function precedent(): BelongsTo
    {
        return $this->belongsTo(Precedent::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(DocumentRequestParty::class);
    }

    public function witnesses(): HasMany
    {
        return $this->hasMany(DocumentRequestWitness::class)->orderBy('position');
    }

    /**
     * Whether this precedent's category requires a human sign-off before a
     * completed draft counts as final. Fails closed (true) if the source
     * precedent has since been deleted — precedent_id is nullable exactly
     * for that case (see the document_requests migration), and there's no
     * way to recover the original review policy once it's gone.
     */
    public function requiresApproval(): bool
    {
        return $this->precedent?->requires_review ?? true;
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Single source of truth for "can this be downloaded right now" —
     * used by both the download route (DownloadDocumentRequestController)
     * and every Filament UI element that shows/hides a Download action, so
     * the rule can't drift between the two.
     */
    public function isReadyForDownload(): bool
    {
        if ($this->status !== 'completed') {
            return false;
        }

        return ! $this->requiresApproval() || $this->isApproved();
    }

    public function isSentForSignature(): bool
    {
        return $this->sent_for_signature_at !== null;
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }
}
