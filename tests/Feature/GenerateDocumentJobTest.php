<?php

namespace Tests\Feature;

use App\Jobs\GenerateDocumentJob;
use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\DocumentGenerationFailedNotification;
use App\Services\DocxBuilder;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
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
            'precedent_id' => $this->makeWillPrecedent()->id,
            'precedent_title_snapshot' => 'Last Will and Testament',
            'requested_by' => $user->id,
            'answers' => $this->willAnswers(),
            'status' => 'pending',
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

    /**
     * DocxBuilder itself is proven correct at the unit level
     * (DocxBuilderTest::test_formatting_overrides_apply_custom_font) — this
     * verifies the wiring one level up: that GenerateDocumentJob actually
     * reads Precedent::formattingConfig() and passes it through as
     * buildAndSave()'s fourth argument, rather than silently dropping it.
     * A mock spy (not a reload-and-inspect) is deliberate: PHPWord's
     * Word2007 reader never restores default-font metadata into the global
     * Settings class on load, so asserting against a reloaded document's
     * getDefaultFontName()/getDefaultFontSize() would just be reading
     * whatever this same process's Settings statics were last set to —
     * true regardless of whether the file itself, or the job's wiring, was
     * actually correct.
     */
    public function test_precedent_level_formatting_override_is_passed_to_docx_builder(): void
    {
        $user = User::factory()->create();
        $precedent = $this->makeWillPrecedent([
            'formatting' => ['font_family' => 'Courier New', 'font_size' => 16],
        ]);
        $documentRequest = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by' => $user->id,
            'answers' => $this->willAnswers(),
            'status' => 'pending',
        ]);

        $this->mock(DocxBuilder::class, function ($mock) {
            $mock->shouldReceive('buildAndSave')
                ->once()
                ->withArgs(fn ($title, $blocks, $path, $formatting) => $formatting === [
                    'font_family' => 'Courier New',
                    'font_size' => 16,
                    'heading_bold' => null,
                    'heading_size_step' => null,
                ]);
        });

        GenerateDocumentJob::dispatchSync($documentRequest);

        $documentRequest->refresh();
        $this->assertSame('completed', $documentRequest->status);
    }

    public function test_unregistered_generator_marks_the_request_failed_with_a_clear_message(): void
    {
        $user = User::factory()->create();
        $precedent = $this->makeWillPrecedent(['generator_class' => 'nonexistent_key']);

        $documentRequest = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => 'Last Will and Testament',
            'requested_by' => $user->id,
            'answers' => $this->willAnswers(),
            'status' => 'pending',
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
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => 'Last Will and Testament',
            'requested_by' => $user->id,
            'answers' => $this->willAnswers(),
            'status' => 'pending',
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
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => 'Last Will and Testament',
            'requested_by' => $user->id,
            'answers' => $this->willAnswers(),
            'status' => 'pending',
        ]);

        GenerateDocumentJob::dispatchSync($documentRequest);

        Notification::assertNothingSent();
    }

    public function test_completion_sends_a_database_notification_to_the_requester(): void
    {
        $user = User::factory()->create();
        $documentRequest = DocumentRequest::create([
            'precedent_id' => $this->makeWillPrecedent()->id,
            'precedent_title_snapshot' => 'Last Will and Testament',
            'requested_by' => $user->id,
            'answers' => $this->willAnswers(),
            'status' => 'pending',
        ]);

        GenerateDocumentJob::dispatchSync($documentRequest);

        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame('Document ready', $user->notifications()->first()->data['title']);
    }

    public function test_completion_notifies_every_user_who_can_approve_when_review_is_required(): void
    {
        $this->seed(ShieldSeeder::class);
        $requester = User::factory()->create();
        $requester->assignRole('panel_user');
        $operator = User::factory()->create();
        $operator->assignRole('operator');
        $unrelatedPanelUser = User::factory()->create();
        $unrelatedPanelUser->assignRole('panel_user');

        // requires_review defaults true — deliberately not overridden.
        $documentRequest = DocumentRequest::create([
            'precedent_id' => $this->makeWillPrecedent()->id,
            'precedent_title_snapshot' => 'Last Will and Testament',
            'requested_by' => $requester->id,
            'answers' => $this->willAnswers(),
            'status' => 'pending',
        ]);

        GenerateDocumentJob::dispatchSync($documentRequest);

        $this->assertSame(1, $operator->notifications()->count());
        $this->assertSame('Document awaiting review', $operator->notifications()->first()->data['title']);
        // A panel_user has no approve permission — never notified as a reviewer.
        $this->assertSame(0, $unrelatedPanelUser->notifications()->count());
    }

    public function test_completion_does_not_notify_reviewers_when_the_precedent_does_not_require_review(): void
    {
        $this->seed(ShieldSeeder::class);
        $operator = User::factory()->create();
        $operator->assignRole('operator');
        $requester = User::factory()->create();

        $documentRequest = DocumentRequest::create([
            'precedent_id' => $this->makeWillPrecedent(['requires_review' => false])->id,
            'precedent_title_snapshot' => 'Last Will and Testament',
            'requested_by' => $requester->id,
            'answers' => $this->willAnswers(),
            'status' => 'pending',
        ]);

        GenerateDocumentJob::dispatchSync($documentRequest);

        $this->assertSame(0, $operator->notifications()->count());
        // The requester still hears about it either way.
        $this->assertSame(1, $requester->notifications()->count());
    }

    public function test_missing_precedent_marks_the_request_failed(): void
    {
        $user = User::factory()->create();
        $precedent = $this->makeWillPrecedent();

        $documentRequest = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => 'Last Will and Testament',
            'requested_by' => $user->id,
            'answers' => $this->willAnswers(),
            'status' => 'pending',
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
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('placeholder — no clause markers at all');
        $tmp = tempnam(sys_get_temp_dir(), 'bad_precedent_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        Storage::disk('local')->put('precedents/bad.docx', file_get_contents($tmp));
        @unlink($tmp);

        $precedent = Precedent::create([
            'title' => 'Broken Precedent', 'docx_path' => 'precedents/bad.docx',
            'generator_class' => 'will', 'questionnaire_fields' => [], 'is_active' => true,
        ]);

        $user = User::factory()->create();
        $documentRequest = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => 'Broken Precedent',
            'requested_by' => $user->id,
            'answers' => $this->willAnswers(),
            'status' => 'pending',
        ]);

        GenerateDocumentJob::dispatchSync($documentRequest);

        $documentRequest->refresh();

        $this->assertSame('failed', $documentRequest->status);
        $this->assertStringContainsString('not found', $documentRequest->error_message);
        $this->assertNull($documentRequest->generated_docx_path);
    }
}
