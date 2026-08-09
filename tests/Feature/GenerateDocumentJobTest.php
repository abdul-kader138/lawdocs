<?php

namespace Tests\Feature;

use App\Jobs\GenerateDocumentJob;
use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\DocumentGenerationFailedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use Tests\Support\WillPrecedentFixture;
use Tests\TestCase;

class GenerateDocumentJobTest extends TestCase
{
    use RefreshDatabase;
    use WillPrecedentFixture;

    public function test_end_to_end_generation_produces_a_downloadable_docx(): void
    {
        $user = User::factory()->create();
        $documentRequest = DocumentRequest::create([
            'precedent_id'             => $this->makeWillPrecedent()->id,
            'precedent_title_snapshot' => 'Last Will and Testament',
            'requested_by'             => $user->id,
            'answers'                  => $this->willAnswers(),
            'status'                   => 'pending',
        ]);

        GenerateDocumentJob::dispatchSync($documentRequest);

        $documentRequest->refresh();

        $this->assertSame('completed', $documentRequest->status);
        $this->assertSame('Last Will and Testament of Ashley Dewell', $documentRequest->generated_title);
        $this->assertNotNull($documentRequest->generated_docx_path);
        $this->assertNotNull($documentRequest->generated_at);
        $this->assertTrue(Storage::disk('local')->exists($documentRequest->generated_docx_path));

        // The generated file must be a genuinely valid, readable .docx.
        $reloaded = IOFactory::load(Storage::disk('local')->path($documentRequest->generated_docx_path), 'Word2007');
        $this->assertCount(1, $reloaded->getSections());
    }

    public function test_unregistered_generator_marks_the_request_failed_with_a_clear_message(): void
    {
        $user = User::factory()->create();
        $precedent = $this->makeWillPrecedent(['generator_class' => 'nonexistent_key']);

        $documentRequest = DocumentRequest::create([
            'precedent_id'             => $precedent->id,
            'precedent_title_snapshot' => 'Last Will and Testament',
            'requested_by'             => $user->id,
            'answers'                  => $this->willAnswers(),
            'status'                   => 'pending',
        ]);

        GenerateDocumentJob::dispatchSync($documentRequest);

        $documentRequest->refresh();

        $this->assertSame('failed', $documentRequest->status);
        $this->assertStringContainsString('No document generator registered', $documentRequest->error_message);
        $this->assertNull($documentRequest->generated_docx_path);
    }

    public function test_failure_notifies_staff_when_email_configured(): void
    {
        Notification::fake();
        Setting::set('staff_notification_email', 'staff@example.com', 'email');

        $user = User::factory()->create();
        $precedent = $this->makeWillPrecedent(['generator_class' => 'nonexistent_key']);

        $documentRequest = DocumentRequest::create([
            'precedent_id'             => $precedent->id,
            'precedent_title_snapshot' => 'Last Will and Testament',
            'requested_by'             => $user->id,
            'answers'                  => $this->willAnswers(),
            'status'                   => 'pending',
        ]);

        GenerateDocumentJob::dispatchSync($documentRequest);

        Notification::assertSentOnDemand(DocumentGenerationFailedNotification::class);
    }

    public function test_failure_skips_notification_when_no_email_configured(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $precedent = $this->makeWillPrecedent(['generator_class' => 'nonexistent_key']);

        $documentRequest = DocumentRequest::create([
            'precedent_id'             => $precedent->id,
            'precedent_title_snapshot' => 'Last Will and Testament',
            'requested_by'             => $user->id,
            'answers'                  => $this->willAnswers(),
            'status'                   => 'pending',
        ]);

        GenerateDocumentJob::dispatchSync($documentRequest);

        Notification::assertNothingSent();
    }

    public function test_missing_precedent_marks_the_request_failed(): void
    {
        $user = User::factory()->create();
        $precedent = $this->makeWillPrecedent();

        $documentRequest = DocumentRequest::create([
            'precedent_id'             => $precedent->id,
            'precedent_title_snapshot' => 'Last Will and Testament',
            'requested_by'             => $user->id,
            'answers'                  => $this->willAnswers(),
            'status'                   => 'pending',
        ]);

        $precedent->delete();

        GenerateDocumentJob::dispatchSync($documentRequest);

        $documentRequest->refresh();

        $this->assertSame('failed', $documentRequest->status);
        $this->assertStringContainsString('no longer exists', $documentRequest->error_message);
    }

    public function test_missing_clause_tag_marks_the_request_failed_not_a_silent_incomplete_document(): void
    {
        Storage::fake('local');
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->addSection()->addText('placeholder — no clause markers at all');
        $tmp = tempnam(sys_get_temp_dir(), 'bad_precedent_') . '.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        Storage::disk('local')->put('precedents/bad.docx', file_get_contents($tmp));
        @unlink($tmp);

        $precedent = Precedent::create([
            'title' => 'Broken Precedent', 'docx_path' => 'precedents/bad.docx',
            'generator_class' => 'will', 'questionnaire_fields' => [], 'is_active' => true,
        ]);

        $user = User::factory()->create();
        $documentRequest = DocumentRequest::create([
            'precedent_id'             => $precedent->id,
            'precedent_title_snapshot' => 'Broken Precedent',
            'requested_by'             => $user->id,
            'answers'                  => $this->willAnswers(),
            'status'                   => 'pending',
        ]);

        GenerateDocumentJob::dispatchSync($documentRequest);

        $documentRequest->refresh();

        $this->assertSame('failed', $documentRequest->status);
        $this->assertStringContainsString('not found', $documentRequest->error_message);
        $this->assertNull($documentRequest->generated_docx_path);
    }
}
