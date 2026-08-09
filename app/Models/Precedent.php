<?php

namespace App\Models;

use App\Exceptions\ClauseMarkerException;
use App\Services\Clause\ClauseMarkerParser;
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
        'extracted_text', 'clause_marker_error', 'questionnaire_fields',
        'generator_class', 'is_active', 'created_by',
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
                $absolutePath = Storage::disk('local')->path($precedent->docx_path);

                $precedent->extracted_text = app(PrecedentTextExtractor::class)->extract($absolutePath);

                // Surfaces a malformed clause marker (unclosed/duplicate/etc)
                // at upload time, next to the extracted_text preview, rather
                // than only when a staff member tries to generate from it.
                try {
                    app(ClauseMarkerParser::class)->parse($absolutePath);
                    $precedent->clause_marker_error = null;
                } catch (ClauseMarkerException $e) {
                    $precedent->clause_marker_error = $e->getMessage();
                }
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
                'type'        => $f['type'] ?? 'text', // text|textarea|number|date|boolean|select
                'required'    => (bool) ($f['required'] ?? false),
                'description' => $f['description'] ?? '',
                'options'     => $f['options'] ?? [],
            ])
            ->toArray();
    }
}
