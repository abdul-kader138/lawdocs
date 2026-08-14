<?php

namespace App\Filament\Resources\DocumentRequestResource\Pages;

use App\Filament\Resources\DocumentRequestResource;
use App\Jobs\GenerateDocumentJob;
use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Models\Setting;
use App\Services\DocumentPreviewBuilder;
use App\Services\DocumentRequestPartyPersister;
use App\Services\DocxToPdfConverter;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;

class CreateDocumentRequest extends CreateRecord
{
    protected static string $resource = DocumentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('previewDocx')
                ->label('Preview Document (.docx)')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action(fn () => $this->preview(pdf: false)),

            Actions\Action::make('previewPdf')
                ->label('Preview Document (PDF)')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('gray')
                ->visible(fn () => app(DocxToPdfConverter::class)->isAvailable())
                ->action(fn () => $this->preview(pdf: true)),
        ];
    }

    /**
     * Renders the wizard's CURRENT (possibly partial, unsubmitted) state via
     * DocumentPreviewBuilder — no DocumentRequest/DocumentRequestParty row
     * survives this, win or lose. getRawState() (not getState()) is
     * deliberate: it skips validation, so staff can preview a form that
     * isn't complete enough to submit yet — that's the point of "see it as
     * you go." Any resulting generator exception (e.g. a null answer a
     * generator doesn't null-guard) is caught here and shown as a graceful
     * notification rather than a 500 — no generator changes are in scope for
     * this feature.
     */
    private function preview(bool $pdf): mixed
    {
        Gate::authorize('create', DocumentRequest::class);

        try {
            $result = app(DocumentPreviewBuilder::class)->build($this->form->getRawState());
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Preview failed')
                ->body($e->getMessage())
                ->send();

            return null;
        }

        $tmpDir = storage_path('app/document-previews/'.Str::uuid());
        File::ensureDirectoryExists($tmpDir);
        $docxPath = $tmpDir.'/preview.docx';
        IOFactory::createWriter($result['phpWord'], 'Word2007')->save($docxPath);

        if (! $pdf) {
            app()->terminating(fn () => File::deleteDirectory($tmpDir));

            return response()->download($docxPath, Str::slug($result['title']).'.docx');
        }

        $converter = app(DocxToPdfConverter::class);
        $pdfPath = $converter->convert($docxPath);
        $workDir = dirname($pdfPath);

        app()->terminating(function () use ($tmpDir, $workDir) {
            File::deleteDirectory($tmpDir);
            File::deleteDirectory($workDir);
        });

        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Str::slug($result['title']).'.pdf"',
        ]);
    }

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

        app(DocumentRequestPartyPersister::class)->persist($documentRequest, $precedent, $data['parties'] ?? []);

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

    protected function getRedirectUrl(): string
    {
        return DocumentRequestResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
