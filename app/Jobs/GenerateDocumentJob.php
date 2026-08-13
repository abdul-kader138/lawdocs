<?php

namespace App\Jobs;

use App\Filament\Resources\DocumentRequestResource;
use App\Models\DocumentRequest;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\DocumentGenerationFailedNotification;
use App\Services\DocxBuilder;
use App\Services\Generators\GeneratorRegistry;
use Filament\Notifications\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Invoked via dispatchSync() in v1 — this is the deliberate seam for an easy
 * async upgrade later (flip to dispatch() + a real QUEUE_CONNECTION) if
 * generation time or concurrent usage ever demands it. Low concurrency and
 * one document per staff action don't justify queue infra yet.
 */
class GenerateDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public DocumentRequest $documentRequest) {}

    public function handle(DocxBuilder $docxBuilder, GeneratorRegistry $registry): void
    {
        $this->documentRequest->update(['status' => 'processing']);

        try {
            $precedent = $this->documentRequest->precedent;

            if (! $precedent) {
                throw new \RuntimeException('The precedent for this request no longer exists.');
            }

            $generator = $registry->resolve($precedent->generator_class);
            $draft = $generator->generate($this->documentRequest);

            $relativePath = 'generated/'.Str::uuid().'.docx';
            Storage::disk('local')->makeDirectory('generated');
            $docxBuilder->buildAndSave(
                $draft['title'],
                $draft['blocks'],
                Storage::disk('local')->path($relativePath),
                $precedent->formattingConfig(),
            );

            $this->documentRequest->update([
                'status' => 'completed',
                'generated_title' => $draft['title'],
                'generated_docx_path' => $relativePath,
                'generation_snapshot' => $draft,
                'generated_at' => now(),
                'error_message' => null,
            ]);

            $this->notifyRequesterOfCompletion();

            // requiresApproval() reads Precedent::requires_review off the
            // already-loaded $precedent relation, so this reflects the same
            // rule DocumentRequest::isReadyForDownload() enforces elsewhere —
            // no separate "does this need review" logic to keep in sync.
            if ($this->documentRequest->requiresApproval()) {
                $this->notifyReviewersOfPendingApproval();
            }
        } catch (\Throwable $e) {
            Log::error('GenerateDocumentJob failed', [
                'document_request_id' => $this->documentRequest->id,
                'error' => $e->getMessage(),
            ]);

            $this->documentRequest->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->notifyStaff();
        }
    }

    private function notifyStaff(): void
    {
        $email = Setting::get('staff_notification_email');
        if (! $email) {
            return;
        }

        try {
            Notification::route('mail', $email)
                ->notify(new DocumentGenerationFailedNotification($this->documentRequest));
        } catch (\Throwable $e) {
            Log::error('Failed to send document generation failure notification', ['error' => $e->getMessage()]);
        }
    }

    /**
     * In-app (database) notification only, deliberately — unlike the
     * failure path this isn't routed to a firm-wide static email, it's
     * addressed to the specific staff member who actually asked for the
     * document, via the panel's notification bell (see
     * AdminPanelProvider::databaseNotifications()).
     */
    private function notifyRequesterOfCompletion(): void
    {
        $requester = $this->documentRequest->requestedBy;
        if (! $requester) {
            return;
        }

        try {
            $notification = FilamentNotification::make()
                ->success()
                ->title('Document ready')
                ->body("\"{$this->documentRequest->generated_title}\" has finished generating.")
                ->icon('heroicon-o-document-check')
                ->actions([
                    FilamentAction::make('view')
                        ->label('View')
                        ->url(DocumentRequestResource::getUrl('view', ['record' => $this->documentRequest]))
                        ->markAsRead(),
                ]);

            // NOT ->sendToDatabase(): Filament's DatabaseNotification
            // implements ShouldQueue, and this app's QUEUE_CONNECTION is
            // "database" with no worker running (see this job's own
            // dispatchSync() docblock — deliberately no queue infra yet).
            // ->notify() would silently leave the notification sitting in
            // the jobs table forever. sendNow() bypasses ShouldQueue and
            // delivers synchronously, matching how the rest of this job runs.
            Notification::sendNow($requester, $notification->toDatabase());
        } catch (\Throwable $e) {
            Log::error('Failed to send document-ready notification', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Everyone who can actually approve (same permission
     * DocumentRequestPolicy::approve() checks) — not the static staff
     * inbox, and not necessarily the requester, who usually can't approve
     * their own request.
     */
    private function notifyReviewersOfPendingApproval(): void
    {
        try {
            // Spatie's permission() scope throws PermissionDoesNotExist if
            // the named permission has no row yet (e.g. Shield hasn't been
            // seeded) — inside the try, not before it, so that's a no-op
            // here rather than a false "generation failed".
            $reviewers = User::permission('update_document::request')->get();
            if ($reviewers->isEmpty()) {
                return;
            }

            $notification = FilamentNotification::make()
                ->warning()
                ->title('Document awaiting review')
                ->body("\"{$this->documentRequest->generated_title}\" is ready for approval before it can be downloaded.")
                ->icon('heroicon-o-shield-exclamation')
                ->actions([
                    FilamentAction::make('view')
                        ->label('Review')
                        ->url(DocumentRequestResource::getUrl('view', ['record' => $this->documentRequest]))
                        ->markAsRead(),
                ]);

            // See notifyRequesterOfCompletion() — same ShouldQueue pitfall.
            Notification::sendNow($reviewers, $notification->toDatabase());
        } catch (\Throwable $e) {
            Log::error('Failed to send needs-review notification', ['error' => $e->getMessage()]);
        }
    }
}
