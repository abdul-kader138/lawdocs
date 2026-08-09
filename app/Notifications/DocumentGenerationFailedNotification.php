<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentGenerationFailedNotification extends Notification
{
    use Queueable;

    public function __construct(private DocumentRequest $documentRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('LawDocs: a document generation failed')
            ->line("Precedent: {$this->documentRequest->precedent_title_snapshot}")
            ->line("Requested by: {$this->documentRequest->requestedBy?->name}")
            ->line("Error: {$this->documentRequest->error_message}")
            ->action('View Request', url("/admin/document-requests/{$this->documentRequest->id}"));
    }
}
