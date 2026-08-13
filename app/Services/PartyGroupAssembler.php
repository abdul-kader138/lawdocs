<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\DocumentRequestParty;
use Carbon\Carbon;

/**
 * Normalizes a DocumentRequest's DocumentRequestParty rows into the shape
 * generators (and, from Phase 2 onward, ClauseTemplateExpander) consume: one
 * array per group_key, ordered by position, each row enriched with
 * synthetic computed fields so neither the generator nor a marker author has
 * to recompute them.
 */
class PartyGroupAssembler
{
    public function __construct(private readonly GrammarResolver $grammar) {}

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function forRequest(DocumentRequest $documentRequest): array
    {
        return $documentRequest->parties()
            ->orderBy('position')
            ->get()
            ->groupBy('group_key')
            ->map(fn ($rows) => $rows->map(fn (DocumentRequestParty $row) => $this->normalize($row))->values()->all())
            ->all();
    }

    private function normalize(DocumentRequestParty $row): array
    {
        $data = $row->data ?? [];
        $data['id'] = $row->id;
        $data['position'] = $row->position;

        // Computed "as at today" rather than stored, so it can never go
        // stale between data entry and generation.
        if (! empty($data['dob'])) {
            try {
                $data['is_minor'] = Carbon::parse($data['dob'])->age < 18;
            } catch (\Throwable) {
                // Malformed date — leave is_minor unset rather than fail generation.
            }
        }

        if (! empty($data['gender'])) {
            $data['pronoun_subject'] = $this->grammar->pronoun($data['gender'], 'subject');
            $data['pronoun_object'] = $this->grammar->pronoun($data['gender'], 'object');
            $data['pronoun_possessive'] = $this->grammar->pronoun($data['gender'], 'possessive');
            $data['pronoun_reflexive'] = $this->grammar->pronoun($data['gender'], 'reflexive');
        }

        if ($row->substitute) {
            $data['substitute'] = $this->normalize($row->substitute);
        }

        return $data;
    }
}
