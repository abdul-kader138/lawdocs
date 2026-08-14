<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrecedentQaBaseline extends Model
{
    protected $fillable = ['precedent_id', 'set_by', 'fingerprint', 'snapshot'];
    protected $casts = ['snapshot' => 'array'];

    public function precedent(): BelongsTo { return $this->belongsTo(Precedent::class); }
    public function setter(): BelongsTo { return $this->belongsTo(User::class, 'set_by'); }
}
