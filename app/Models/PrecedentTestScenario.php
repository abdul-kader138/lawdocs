<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrecedentTestScenario extends Model
{
    protected $fillable = ['precedent_id', 'name', 'answers', 'parties', 'expected_title', 'expected_includes', 'expected_excludes', 'is_active'];

    protected $casts = [
        'answers' => 'array', 'parties' => 'array', 'expected_includes' => 'array',
        'expected_excludes' => 'array', 'is_active' => 'boolean',
    ];

    public function precedent(): BelongsTo
    {
        return $this->belongsTo(Precedent::class);
    }
}
