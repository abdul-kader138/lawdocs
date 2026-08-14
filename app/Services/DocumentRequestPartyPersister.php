<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\DocumentRequestParty;
use App\Models\Precedent;
use App\Support\PartyGroupFormBuilder;

/**
 * Extracted from CreateDocumentRequest so the exact same two-pass
 * insert/substitute-resolve logic can be reused by DocumentPreviewBuilder
 * without the two call sites drifting apart.
 */
class DocumentRequestPartyPersister
{
    /**
     * Two passes per group: first insert every row (so each gets a real id),
     * THEN resolve any "_substitute_ref" selection into a real
     * substitute_party_id by matching it against the sibling rows' own
     * primary field text (see PartyGroupFormBuilder::substituteRefField()
     * for why matching-by-text, not by a UUID, is the mechanism that
     * actually survives Filament's Repeater dehydration). The UI-only
     * "_substitute_ref" key is stripped from $row before it's stored — it
     * must never leak into the persisted data (it isn't a real party field,
     * and would otherwise show up as an "unknown field" the next time a
     * marker author cross-checks {{...}} placeholders against
     * party_groups[].fields).
     *
     * @param  array<string, array<int, array<string, mixed>>>  $partiesByGroup
     */
    public function persist(DocumentRequest $documentRequest, Precedent $precedent, array $partiesByGroup): void
    {
        $groupsByKey = collect($precedent->partyGroupsConfig())->keyBy('key');

        foreach ($partiesByGroup as $groupKey => $rows) {
            $primaryField = $groupsByKey[$groupKey]['fields'][0]['name'] ?? null;

            $partyIdByPrimaryValue = [];
            $pendingSubstitutes = []; // partyId => substitute's primary-field text
            $position = 0;

            foreach (array_values($rows) as $row) {
                $substituteRef = $row[PartyGroupFormBuilder::SUBSTITUTE_REF_FIELD] ?? null;
                unset($row[PartyGroupFormBuilder::SUBSTITUTE_REF_FIELD]);

                $party = $documentRequest->parties()->create([
                    'group_key' => $groupKey,
                    'position' => $position++,
                    'data' => $row,
                ]);

                if ($primaryField && filled($row[$primaryField] ?? null)) {
                    $partyIdByPrimaryValue[$row[$primaryField]] = $party->id;
                }

                if (filled($substituteRef)) {
                    $pendingSubstitutes[$party->id] = $substituteRef;
                }
            }

            foreach ($pendingSubstitutes as $partyId => $substituteValue) {
                $substituteId = $partyIdByPrimaryValue[$substituteValue] ?? null;
                if ($substituteId !== null && $substituteId !== $partyId) {
                    DocumentRequestParty::whereKey($partyId)->update(['substitute_party_id' => $substituteId]);
                }
            }
        }
    }
}
