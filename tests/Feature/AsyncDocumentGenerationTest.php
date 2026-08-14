<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentRequestResource\Pages\CreateDocumentRequest;
use App\Jobs\GenerateDocumentJob;
use App\Models\DocumentRequest;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\WillPrecedentFixture;
use Tests\TestCase;

/**
 * Covers the async_generation_enabled branch of
 * CreateDocumentRequest::handleRecordCreation() — previously wired but
 * untested. GenerateDocumentJob already implements ShouldQueue; this only
 * proves the dispatch/notification/status side, not that a real worker
 * picks the job up (that's infra — see deploy/lawdocs-queue-worker.service
 * and deploy.sh — not something a test can exercise).
 */
class AsyncDocumentGenerationTest extends TestCase
{
    use RefreshDatabase;
    use WillPrecedentFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShieldSeeder::class);
    }

    private function willQuestionnaireFields(): array
    {
        return [
            ['name' => 'testator_name', 'label' => "Testator's Name", 'type' => 'text', 'required' => true, 'description' => 'Full legal name'],
            ['name' => 'testator_street', 'label' => 'Street', 'type' => 'text', 'required' => true, 'description' => ''],
            ['name' => 'testator_suburb', 'label' => 'Suburb', 'type' => 'text', 'required' => true, 'description' => ''],
            ['name' => 'testator_state', 'label' => 'State', 'type' => 'text', 'required' => true, 'description' => ''],
            ['name' => 'testator_gender', 'label' => 'Gender', 'type' => 'select', 'required' => true, 'description' => '', 'options' => ['male' => 'Male', 'female' => 'Female']],
            ['name' => 'executor_name', 'label' => 'Executor Name', 'type' => 'text', 'required' => true, 'description' => ''],
            ['name' => 'executor_gender', 'label' => 'Executor Gender', 'type' => 'select', 'required' => true, 'description' => '', 'options' => ['male' => 'Male', 'female' => 'Female']],
        ];
    }

    public function test_creating_a_request_with_async_generation_enabled_queues_the_job_instead_of_running_it_inline(): void
    {
        Setting::set('async_generation_enabled', true);
        Queue::fake();

        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        $precedent = $this->makeWillPrecedent(['questionnaire_fields' => $this->willQuestionnaireFields(), 'requires_review' => false]);

        $this->actingAs($staff);

        Livewire::test(CreateDocumentRequest::class)
            ->fillForm([
                'precedent_id' => $precedent->id,
                'answers' => $this->willAnswers(),
                'parties' => ['beneficiaries' => $this->willBeneficiaryRows()],
                'case_reference' => 'MATTER-ASYNC-001',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $documentRequest = DocumentRequest::where('case_reference', 'MATTER-ASYNC-001')->firstOrFail();

        // Not generated inline — Queue::fake() intercepted the job, nothing
        // actually ran it, so status must still be exactly what
        // DocumentRequest::create() set it to.
        $this->assertSame('pending', $documentRequest->status);
        $this->assertNull($documentRequest->generated_docx_path);

        Queue::assertPushed(GenerateDocumentJob::class, fn ($job) => $job->documentRequest->is($documentRequest));
    }

    public function test_creating_a_request_with_async_generation_disabled_still_runs_inline(): void
    {
        // Deliberately no Queue::fake() here — dispatchSync() is intercepted
        // by the fake just like dispatch() is, so proving this path
        // actually generates synchronously means letting it really run.
        Setting::set('async_generation_enabled', false);

        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        $precedent = $this->makeWillPrecedent(['questionnaire_fields' => $this->willQuestionnaireFields(), 'requires_review' => false]);

        $this->actingAs($staff);

        Livewire::test(CreateDocumentRequest::class)
            ->fillForm([
                'precedent_id' => $precedent->id,
                'answers' => $this->willAnswers(),
                'parties' => ['beneficiaries' => $this->willBeneficiaryRows()],
                'case_reference' => 'MATTER-SYNC-001',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $documentRequest = DocumentRequest::where('case_reference', 'MATTER-SYNC-001')->firstOrFail();

        $this->assertSame('completed', $documentRequest->status);
        $this->assertNotNull($documentRequest->generated_docx_path);
    }
}
