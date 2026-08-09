<?php

namespace App\Jobs;

use App\Models\DocumentRequest;
use App\Models\Setting;
use App\Notifications\DocumentGenerationFailedNotification;
use App\Services\DocxBuilder;
use App\Services\Generators\GeneratorRegistry;
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
            $draft     = $generator->generate($precedent, $this->documentRequest->answers ?? []);

            $relativePath = 'generated/' . Str::uuid() . '.docx';
            Storage::disk('local')->makeDirectory('generated');
            $docxBuilder->buildAndSave($draft['title'], $draft['blocks'], Storage::disk('local')->path($relativePath));

            $this->documentRequest->update([
                'status'               => 'completed',
                'generated_title'      => $draft['title'],
                'generated_docx_path'  => $relativePath,
                'generation_snapshot'  => $draft,
                'generated_at'         => now(),
                'error_message'        => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateDocumentJob failed', [
                'document_request_id' => $this->documentRequest->id,
                'error'                => $e->getMessage(),
            ]);

            $this->documentRequest->update([
                'status'        => 'failed',
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
}
