<?php

namespace App\Filament\Resources\DocumentRequestResource\Pages;

use App\Filament\Resources\DocumentRequestResource;
use App\Models\DocumentRequest;
use App\Services\DocxToPdfConverter;
use App\Support\JurisdictionRules;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

class ViewDocumentRequest extends ViewRecord
{
    protected static string $resource = DocumentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewPdf')
                ->label('Preview (PDF)')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (DocumentRequest $record) => route('document-requests.preview-pdf', $record))
                ->openUrlInNewTab()
                ->visible(fn (DocumentRequest $record) => $record->status === 'completed'
                    && app(DocxToPdfConverter::class)->isAvailable()),

            Action::make('approve')
                ->label('Approve for Download')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('This confirms the generated draft has been reviewed and may be downloaded. This does not check the wording for you.')
                ->action(fn (DocumentRequest $record) => $record->update(['approved_at' => now(), 'approved_by' => auth()->id()]))
                ->visible(fn (DocumentRequest $record) => $record->status === 'completed'
                    && $record->requiresApproval()
                    && ! $record->isApproved()
                    && Gate::allows('approve', $record)),

            Action::make('download')
                ->label('Download .docx')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (DocumentRequest $record) => route('document-requests.download', $record))
                ->openUrlInNewTab()
                ->visible(fn (DocumentRequest $record) => $record->isReadyForDownload()),

            Action::make('downloadPdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn (DocumentRequest $record) => route('document-requests.download-pdf', $record))
                ->openUrlInNewTab()
                ->visible(fn (DocumentRequest $record) => $record->isReadyForDownload()
                    && app(DocxToPdfConverter::class)->isAvailable()),

            Action::make('sendForSignature')
                ->label('Send for Signature')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Marks this document as sent for signing (outside this app — print/e-sign as the firm normally handles execution). This is a workflow record, not a live e-signature integration.')
                ->action(fn (DocumentRequest $record) => $record->update(['sent_for_signature_at' => now()]))
                ->visible(fn (DocumentRequest $record) => $record->isReadyForDownload() && ! $record->isSentForSignature()),

            Action::make('markAsSigned')
                ->label('Mark as Signed')
                ->icon('heroicon-o-pencil')
                ->color('success')
                ->form([
                    FileUpload::make('signed_document_path')
                        ->label('Executed Document (optional)')
                        ->helperText('The wet-signed scan or externally e-signed copy, if you have one ready to attach.')
                        ->disk('local')
                        ->directory('signed')
                        ->visibility('private'),
                ])
                ->action(function (DocumentRequest $record, array $data) {
                    $record->update([
                        'signed_at' => now(),
                        'signed_document_path' => $data['signed_document_path'] ?? $record->signed_document_path,
                    ]);

                    Notification::make()->success()->title('Marked as signed')->send();
                })
                ->visible(fn (DocumentRequest $record) => $record->isSentForSignature() && ! $record->isSigned()),

            Action::make('downloadSignedCopy')
                ->label('Download Signed Copy')
                ->icon('heroicon-o-document-check')
                ->color('gray')
                ->url(fn (DocumentRequest $record) => route('document-requests.download-signed', $record))
                ->openUrlInNewTab()
                ->visible(fn (DocumentRequest $record) => filled($record->signed_document_path)),

            Action::make('regenerate')
                ->label('Regenerate')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->url(fn (DocumentRequest $record) => DocumentRequestResource::getUrl('create', ['regenerate_from' => $record->id])),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Request')->schema([
                TextEntry::make('precedent_title_snapshot')->label('Precedent'),
                TextEntry::make('client.name')->label('Client')->placeholder('—'),
                TextEntry::make('requestedBy.name')->label('Requested By'),
                TextEntry::make('case_reference')->label('Case Reference')->placeholder('—'),
                TextEntry::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'completed' => 'success',
                    'failed' => 'danger',
                    'processing' => 'warning',
                    default => 'gray',
                }),
                TextEntry::make('error_message')->label('Error')->visible(fn (DocumentRequest $record) => $record->status === 'failed'),
                TextEntry::make('generated_title')->label('Generated Title')->placeholder('—'),
                TextEntry::make('generated_at')->dateTime()->placeholder('—'),
                TextEntry::make('approval_status')
                    ->label('Approval')
                    ->state(function (DocumentRequest $record) {
                        if ($record->status !== 'completed') {
                            return '—';
                        }

                        return match (true) {
                            ! $record->requiresApproval() => 'Not required',
                            $record->isApproved() => 'Approved',
                            default => 'Needs review',
                        };
                    })
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Approved' => 'success',
                        'Needs review' => 'warning',
                        default => 'gray',
                    }),
                TextEntry::make('approver.name')
                    ->label('Approved By')
                    ->placeholder('—')
                    ->visible(fn (DocumentRequest $record) => $record->isApproved()),
            ])->columns(2),

            Section::make('Signing')
                ->visible(fn (DocumentRequest $record) => $record->isReadyForDownload())
                ->schema([
                    TextEntry::make('signing_status')
                        ->label('Status')
                        ->state(function (DocumentRequest $record) {
                            return match (true) {
                                $record->isSigned() => 'Signed',
                                $record->isSentForSignature() => 'Sent for signature',
                                default => 'Not sent',
                            };
                        })
                        ->badge()
                        ->color(fn (string $state) => match ($state) {
                            'Signed' => 'success',
                            'Sent for signature' => 'warning',
                            default => 'gray',
                        }),
                    TextEntry::make('sent_for_signature_at')->label('Sent')->dateTime()->placeholder('—'),
                    TextEntry::make('signed_at')->label('Signed')->dateTime()->placeholder('—'),
                    TextEntry::make('witness_guidance')
                        ->label('Witnesses')
                        ->columnSpanFull()
                        ->state(function (DocumentRequest $record) {
                            $precedent = $record->precedent;
                            $minimum = $precedent ? JurisdictionRules::minimumWitnesses($precedent->jurisdiction, $precedent->category) : null;
                            if ($minimum === null) {
                                return null;
                            }

                            $recorded = $record->witnesses()->count();

                            return "{$precedent->jurisdiction} guidance: {$minimum} witness".($minimum === 1 ? '' : 'es')
                                ." ordinarily expected for this document type — {$recorded} recorded below. Not a hard rule; confirm actual requirements with a solicitor.";
                        })
                        ->visible(fn (DocumentRequest $record) => $record->precedent
                            && JurisdictionRules::minimumWitnesses($record->precedent->jurisdiction, $record->precedent->category) !== null),
                ])->columns(3),

            Section::make('Submitted Answers')->schema([
                KeyValueEntry::make('answers')->label(''),
            ]),
        ]);
    }
}
