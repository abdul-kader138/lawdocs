<?php

namespace App\Filament\Resources\DocumentRequestResource\Pages;

use App\Filament\Resources\DocumentRequestResource;
use App\Jobs\GenerateDocumentJob;
use App\Models\DocumentRequest;
use App\Models\Precedent;
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
                    'precedent_id' => $source->precedent_id,
                    'answers'      => $source->answers,
                ]);
            }
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $precedent = Precedent::findOrFail($data['precedent_id']);

        $documentRequest = DocumentRequest::create([
            'precedent_id'             => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by'             => auth()->id(),
            'case_reference'           => $data['case_reference'] ?? null,
            'answers'                  => $data['answers'] ?? [],
            'status'                   => 'pending',
        ]);

        // Synchronous in v1 — see GenerateDocumentJob's docblock for why.
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

    protected function getRedirectUrl(): string
    {
        return DocumentRequestResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
