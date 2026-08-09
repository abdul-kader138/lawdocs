<?php

namespace App\Models;

use App\Services\PrecedentTextExtractor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Precedent extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'category', 'description', 'docx_path', 'docx_original_filename',
        'extracted_text', 'questionnaire_fields', 'is_active', 'created_by',
    ];

    protected $casts = [
        'questionnaire_fields' => 'array',
        'is_active'            => 'boolean',
    ];

    protected static function booted(): void
    {
        // Runs on any save path (Filament form, seeder, tinker) — not tied to
        // the admin form specifically, so extraction always stays in sync
        // with whatever file is actually stored.
        static::saving(function (Precedent $precedent) {
            if ($precedent->isDirty('docx_path') && $precedent->docx_path) {
                $precedent->extracted_text = app(PrecedentTextExtractor::class)
                    ->extract(Storage::disk('local')->path($precedent->docx_path));
            }
        });
    }

    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Mirrors wma-bot's Service::toConfig() keyBy/map normalization pattern.
    public function questionnaireFieldsConfig(): array
    {
        return collect($this->questionnaire_fields ?? [])
            ->keyBy('name')
            ->map(fn ($f) => [
                'label'       => $f['label'] ?? $f['name'],
                'type'        => $f['type'] ?? 'text', // text|textarea|number|date|boolean
                'required'    => (bool) ($f['required'] ?? false),
                'description' => $f['description'] ?? '',
            ])
            ->toArray();
    }
}
