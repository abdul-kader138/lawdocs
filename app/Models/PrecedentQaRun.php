<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrecedentQaRun extends Model
{
    protected $fillable = ['precedent_id', 'run_by', 'fingerprint', 'status', 'issues', 'scenario_results', 'comparison', 'snapshot'];

    protected $casts = ['issues' => 'array', 'scenario_results' => 'array', 'comparison' => 'array', 'snapshot' => 'array'];

    public function precedent(): BelongsTo { return $this->belongsTo(Precedent::class); }
    public function runner(): BelongsTo { return $this->belongsTo(User::class, 'run_by'); }
}
