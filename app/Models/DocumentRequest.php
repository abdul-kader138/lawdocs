<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'precedent_id', 'precedent_title_snapshot', 'requested_by', 'case_reference',
        'answers', 'status', 'error_message', 'generated_docx_path', 'generated_title',
        'generation_snapshot', 'generated_at',
    ];

    protected $casts = [
        'answers'              => 'array',
        'generation_snapshot'  => 'array',
        'generated_at'         => 'datetime',
    ];

    public function precedent(): BelongsTo
    {
        return $this->belongsTo(Precedent::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
