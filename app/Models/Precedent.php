<?php

namespace App\Models;

use App\Contracts\DeclaresComputedBlocks;
use App\Contracts\DeclaresPartyFlags;
use App\Exceptions\ClauseMarkerException;
use App\Services\AnswerContextBuilder;
use App\Services\Clause\ClauseMarkerParser;
use App\Services\Clause\ClauseMarkerValidator;
use App\Services\Generators\GeneratorRegistry;
use App\Services\PrecedentTextExtractor;
use App\Support\DocumentFormattingProfiles;
use App\Support\PrecedentFlagResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Precedent extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('precedent');
    }

    protected $fillable = [
        'title', 'output_title_template', 'category', 'description', 'docx_path', 'docx_original_filename',
        'extracted_text', 'clause_marker_error', 'questionnaire_fields', 'party_groups',
        'clause_sequence', 'formatting', 'generator_class', 'is_active', 'created_by',
        'jurisdiction', 'requires_review', 'client_field_map',
    ];

    protected $casts = [
        'questionnaire_fields' => 'array',
        'party_groups' => 'array',
        'clause_sequence' => 'array',
        'formatting' => 'array',
        'is_active' => 'boolean',
        'requires_review' => 'boolean',
        'client_field_map' => 'array',
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
                    $tree = app(ClauseMarkerParser::class)->parse($absolutePath);
                    $precedent->clause_marker_error = static::validateMarkerReferences($precedent, $tree);
                } catch (ClauseMarkerException $e) {
                    $precedent->clause_marker_error = $e->getMessage();
                }
            }
        });
    }

    /**
     * Cross-checks every [[IF:...]] flag, [[REPEAT:group]] group key, and
     * {{ns.field}} placeholder found in the parsed tree against the
     * assigned generator's documented flags/groups (DeclaresPartyFlags)
     * plus this precedent's own questionnaire_fields/party_groups —
     * catching an admin's typo at upload time instead of at first real
     * generation attempt. Silently skips (nothing to validate against) if
     * generator_class isn't set/resolvable yet, or the generator doesn't
     * implement DeclaresPartyFlags.
     */
    private static function validateMarkerReferences(self $precedent, array $tree): ?string
    {
        try {
            $generator = app(GeneratorRegistry::class)->resolve($precedent->generator_class);
        } catch (\RuntimeException) {
            return null;
        }

        if (! $generator instanceof DeclaresPartyFlags) {
            return null;
        }

        $answerFieldNames = collect($precedent->questionnaire_fields ?? [])->pluck('name')->filter()->values()->all();
        $answerFieldNames = AnswerContextBuilder::derivedAnswerFieldNames($answerFieldNames);

        $partyGroupFieldsByKey = collect($precedent->party_groups ?? [])
            ->filter(fn ($g) => filled($g['key'] ?? null))
            ->mapWithKeys(function ($g) {
                $fieldNames = collect($g['fields'] ?? [])->pluck('name')->filter()->values()->all();

                // PartyGroupFormBuilder auto-adds a "per_stirpes" field when
                // this toggle is on (never declared in party_groups[].fields
                // itself) — see PartyGroupFormBuilder::buildRepeater().
                if ($g['supports_per_stirpes'] ?? false) {
                    $fieldNames[] = 'per_stirpes';
                }

                return [$g['key'] => $fieldNames];
            })
            ->all();

        $validator = app(ClauseMarkerValidator::class);

        // Every questionnaire field (any type) is a valid [[IF:...]] target
        // and every configured party group is REPEAT-available, for every
        // generator — not just the no-code template generator — merged with
        // whatever extra flags/groups the generator itself declares (e.g.
        // WillGenerator's REPEAT-scoped "beneficiary.per_stirpes"). See
        // PrecedentFlagResolver/AnswerContextBuilder.
        $availableFlags = PrecedentFlagResolver::availableFlags($precedent->questionnaire_fields ?? [], $generator);
        $availableGroups = PrecedentFlagResolver::availableGroups($precedent->party_groups ?? [], $generator);

        $issues = $validator->validate(
            $tree,
            $answerFieldNames,
            $partyGroupFieldsByKey,
            $availableFlags,
            $availableGroups,
        );

        $issues = [...$issues, ...static::validateClauseSequence($precedent, $tree, $generator, $validator, $availableFlags)];

        if ($precedent->generator_class === 'template' && $precedent->clauseSequenceConfig() === [] && $tree === []) {
            $issues[] = 'The no-code template generator requires at least one [[CLAUSE:...]] block.';
        }

        return $issues === [] ? null : implode("\n", $issues);
    }

    /**
     * Cross-checks every clause_sequence entry: a kind:'clause' tag_or_key
     * must exist in the parsed marker tree, a kind:'computed' tag_or_key
     * must be one of the assigned generator's availableComputedBlocks(),
     * and any "show only if" condition must only reference flags the
     * generator actually declares — same typo-at-save-time guarantee as
     * marker/placeholder validation above, surfaced through the same
     * clause_marker_error field.
     *
     * @param  array<string, array<int, mixed>>  $tree
     * @return array<int, string>
     */
    private static function validateClauseSequence(self $precedent, array $tree, DeclaresPartyFlags $generator, ClauseMarkerValidator $validator, ?array $availableFlags = null): array
    {
        $entries = $precedent->clauseSequenceConfig();
        if ($entries === []) {
            return [];
        }

        $tags = array_keys($tree);
        $computedKeys = $generator instanceof DeclaresComputedBlocks
            ? array_keys($generator->availableComputedBlocks())
            : [];

        $issues = [];
        $conditions = [];

        foreach ($entries as $entry) {
            $label = "Structure \"{$entry['heading']}\"";

            if ($entry['kind'] === 'clause') {
                if (! in_array($entry['tag_or_key'], $tags, true)) {
                    $issues[] = "{$label}: references unknown clause tag \"{$entry['tag_or_key']}\". "
                        .($tags === [] ? 'No [[CLAUSE:...]] tags found in the uploaded .docx.' : 'Valid tags: '.implode(', ', $tags));
                }
            } elseif (! in_array($entry['tag_or_key'], $computedKeys, true)) {
                $issues[] = "{$label}: references unknown computed block \"{$entry['tag_or_key']}\". "
                    .($computedKeys === [] ? 'This generator declares no computed blocks.' : 'Valid keys: '.implode(', ', $computedKeys));
            }

            if ($entry['condition'] !== null) {
                $conditions[] = ['label' => $label, 'condition' => $entry['condition']];
            }
        }

        return [...$issues, ...$validator->validateConditions($conditions, $availableFlags ?? $generator->availableFlags())];
    }

    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }

    public function testScenarios(): HasMany
    {
        return $this->hasMany(PrecedentTestScenario::class);
    }

    public function qaRuns(): HasMany
    {
        return $this->hasMany(PrecedentQaRun::class);
    }

    public function qaBaseline(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PrecedentQaBaseline::class);
    }

    /**
     * Repeatable party-role groups (Beneficiaries, Executors, Guardians, ...)
     * declared for this precedent. Schema-only, like questionnaire_fields —
     * the actual per-request data lives in DocumentRequestParty rows.
     */
    public function partyGroupsConfig(): array
    {
        return collect($this->party_groups ?? [])
            ->map(fn ($g) => [
                'key' => $g['key'],
                'label' => $g['label'] ?? $g['key'],
                'role_type' => $g['role_type'] ?? 'custom',
                'min_items' => (int) ($g['min_items'] ?? 0),
                'max_items' => isset($g['max_items']) && $g['max_items'] !== '' ? (int) $g['max_items'] : null,
                'share_field' => $g['share_field'] ?: null,
                'supports_substitute' => (bool) ($g['supports_substitute'] ?? false),
                'supports_per_stirpes' => (bool) ($g['supports_per_stirpes'] ?? false),
                'fields' => collect($g['fields'] ?? [])
                    ->map(fn ($f) => [
                        'name' => $f['name'],
                        'label' => $f['label'] ?? $f['name'],
                        'type' => $f['type'] ?? 'text',
                        'required' => (bool) ($f['required'] ?? false),
                        'description' => $f['description'] ?? '',
                        'options' => $f['options'] ?? [],
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Admin-authored section list controlling what this precedent's
     * generated document contains, in what order, and whether each section
     * is shown — see ClauseSequenceRenderer, which reads this instead of a
     * generator's hardcoded sequence whenever it's non-empty. Structural
     * normalization only (drops entries with no tag_or_key, defaults a
     * blank heading to a humanized tag_or_key, defaults kind to 'clause');
     * whether a tag/key/condition actually resolves is checked separately
     * in validateClauseSequence() at save time, same split as
     * questionnaireFieldsConfig()/partyGroupsConfig() vs their own
     * marker-reference validation.
     *
     * @return array<int, array{heading: string, kind: string, tag_or_key: string, condition: ?string}>
     */
    public function clauseSequenceConfig(): array
    {
        return collect($this->clause_sequence ?? [])
            ->filter(fn ($e) => filled($e['tag_or_key'] ?? null))
            ->map(function ($e) {
                $tagOrKey = $e['tag_or_key'];
                $kind = ($e['kind'] ?? null) === 'computed' ? 'computed' : 'clause';

                return [
                    'heading' => filled($e['heading'] ?? null)
                        ? $e['heading']
                        : str($tagOrKey)->replace('_', ' ')->title()->toString(),
                    'kind' => $kind,
                    'tag_or_key' => $tagOrKey,
                    'condition' => filled($e['condition'] ?? null) ? $e['condition'] : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Admin-authored per-precedent override of the global Document Defaults
     * font/heading style — see DocxBuilder::build()'s $formattingOverrides
     * param, which falls back to the global Setting/hardcoded default for
     * any key returned null here. Mirrors partyGroupsConfig()'s
     * isset()/!== '' normalization (not a naive ??) since a blank Filament
     * TextInput/Select can dehydrate as '' rather than null.
     *
     * @return array<string, mixed>
     */
    public function formattingConfig(): array
    {
        $stored = $this->formatting ?? [];
        $f = [...DocumentFormattingProfiles::values($stored['profile'] ?? null), ...$stored];

        return [
            'font_family' => isset($f['font_family']) && $f['font_family'] !== '' ? (string) $f['font_family'] : null,
            'font_size' => isset($f['font_size']) && $f['font_size'] !== '' ? (int) $f['font_size'] : null,
            'heading_bold' => isset($f['heading_bold']) && $f['heading_bold'] !== '' ? (bool) (int) $f['heading_bold'] : null,
            'heading_size_step' => isset($f['heading_size_step']) && $f['heading_size_step'] !== '' ? (int) $f['heading_size_step'] : null,
            'body_alignment' => filled($f['body_alignment'] ?? null) ? (string) $f['body_alignment'] : null,
            'line_spacing' => isset($f['line_spacing']) && $f['line_spacing'] !== '' ? (float) $f['line_spacing'] : null,
            'paragraph_space_after' => isset($f['paragraph_space_after']) && $f['paragraph_space_after'] !== '' ? (float) $f['paragraph_space_after'] : null,
            'first_line_indent' => isset($f['first_line_indent']) && $f['first_line_indent'] !== '' ? (float) $f['first_line_indent'] : null,
            'left_indent' => isset($f['left_indent']) && $f['left_indent'] !== '' ? (float) $f['left_indent'] : null,
            'right_indent' => isset($f['right_indent']) && $f['right_indent'] !== '' ? (float) $f['right_indent'] : null,
            'apply_paragraph_style_to_clauses' => isset($f['apply_paragraph_style_to_clauses']) && $f['apply_paragraph_style_to_clauses'] !== '' ? (bool) $f['apply_paragraph_style_to_clauses'] : null,
            'margin_top' => isset($f['margin_top']) && $f['margin_top'] !== '' ? (float) $f['margin_top'] : null,
            'margin_right' => isset($f['margin_right']) && $f['margin_right'] !== '' ? (float) $f['margin_right'] : null,
            'margin_bottom' => isset($f['margin_bottom']) && $f['margin_bottom'] !== '' ? (float) $f['margin_bottom'] : null,
            'margin_left' => isset($f['margin_left']) && $f['margin_left'] !== '' ? (float) $f['margin_left'] : null,
            'footer_text' => filled($f['footer_text'] ?? null) ? (string) $f['footer_text'] : null,
            'page_numbers' => isset($f['page_numbers']) && $f['page_numbers'] !== '' ? (bool) $f['page_numbers'] : null,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Admin-declared mapping: Client attribute (name/dob/street/...) =>
     * questionnaire_fields name it should prefill (e.g. 'name' => 'testator_name'
     * on a Will, 'name' => 'principal_name' on a POA) — filters out any
     * attribute left unmapped, so the wizard only ever touches fields the
     * admin actually opted into prefilling.
     *
     * @return array<string, string>
     */
    public function clientFieldMapConfig(): array
    {
        return collect($this->client_field_map ?? [])
            ->filter(fn ($fieldName) => filled($fieldName))
            ->all();
    }

    // Mirrors wma-bot's Service::toConfig() keyBy/map normalization pattern.
    public function questionnaireFieldsConfig(): array
    {
        return collect($this->questionnaire_fields ?? [])
            ->keyBy('name')
            ->map(fn ($f) => [
                'label' => $f['label'] ?? $f['name'],
                'type' => $f['type'] ?? 'text', // text|textarea|number|date|boolean|select
                'required' => (bool) ($f['required'] ?? false),
                'description' => $f['description'] ?? '',
                'options' => $f['options'] ?? [],
            ])
            ->toArray();
    }
}
