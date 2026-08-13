<?php

namespace App\Filament\Resources\DocumentRequestResource\Pages;

use App\Filament\Resources\DocumentRequestResource;
use App\Jobs\GenerateDocumentJob;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestParty;
use App\Models\Precedent;
use App\Models\Setting;
use App\Support\PartyGroupFormBuilder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDocumentRequest extends CreateRecord
{
    protected static string $resource = DocumentRequestResource::class;

    public function mount(): void
    {
        parent::mount();

        // "Regenerate" from an existing request pre-fills the same precedent
        // and answers so staff only need to adjust what's changed.
        if ($sourceId = request()->query('regenerate_from')) {
            $source = DocumentRequest::find($sourceId);

            if ($source) {
                $this->form->fill([
                    'client_id' => $source->client_id,
                    'precedent_id' => $source->precedent_id,
                    'answers' => $source->answers,
                    'parties' => $source->parties()
                        ->orderBy('position')
                        ->get()
                        ->groupBy('group_key')
                        ->map(fn ($rows) => $rows->pluck('data')->values()->all())
                        ->all(),
                ]);
            }
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $precedent = Precedent::findOrFail($data['precedent_id']);

        $documentRequest = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'precedent_jurisdiction_snapshot' => $precedent->jurisdiction,
            'client_id' => $data['client_id'] ?? null,
            'requested_by' => auth()->id(),
            'case_reference' => $data['case_reference'] ?? null,
            'answers' => $data['answers'] ?? [],
            'status' => 'pending',
        ]);

        $this->persistParties($documentRequest, $precedent, $data['parties'] ?? []);

        // Synchronous by default (dispatchSync) — see GenerateDocumentJob's
        // docblock. Async is opt-in via System Settings > Document Defaults,
        // since dispatch() silently does nothing without a queue worker
        // actually running (see GenerateDocumentJob's notification methods
        // for the exact failure mode this sidesteps).
        if ((bool) Setting::get('async_generation_enabled', false)) {
            GenerateDocumentJob::dispatch($documentRequest);

            Notification::make()
                ->success()
                ->title('Document queued')
                ->body('Generating in the background — you\'ll be notified here when it\'s ready.')
                ->send();

            return $documentRequest;
        }

        GenerateDocumentJob::dispatchSync($documentRequest);

        $documentRequest->refresh();

        if ($documentRequest->status === 'failed') {
            Notification::make()
                ->danger()
                ->title('Draft generation failed')
                ->body($documentRequest->error_message)
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->success()
                ->title('Document drafted')
                ->send();
        }

        return $documentRequest;
    }

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
    private function persistParties(DocumentRequest $documentRequest, Precedent $precedent, array $partiesByGroup): void
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

    protected function getRedirectUrl(): string
    {
        return DocumentRequestResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
