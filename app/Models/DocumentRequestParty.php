<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class DocumentRequestParty extends Model
{
    use LogsActivity;

    protected $fillable = [
        'document_request_id', 'group_key', 'position', 'data', 'substitute_party_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('document_request_party');
    }

    protected $casts = [
        'data' => 'array',
    ];

    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(self::class, 'substitute_party_id');
    }
}
