<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentRequestResource\Pages\CreateDocumentRequest;
use App\Filament\Resources\DocumentRequestResource\Pages\ViewDocumentRequest;
use App\Models\DocumentRequest;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Support\WillPrecedentFixture;
use Tests\TestCase;

class FilamentAccessAndWizardTest extends TestCase
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

    public function test_super_admin_can_access_precedent_resource(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->actingAs($admin)->get('/admin/precedents')->assertOk();
        $this->actingAs($admin)->get('/admin/precedents/create')->assertOk();
    }

    public function test_panel_user_cannot_access_precedent_resource_at_all(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');

        $this->actingAs($staff)->get('/admin/precedents')->assertForbidden();
        $this->actingAs($staff)->get('/admin/precedents/create')->assertForbidden();
    }

    public function test_panel_user_can_access_document_request_resource(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');

        $this->actingAs($staff)->get('/admin/document-requests')->assertOk();
        $this->actingAs($staff)->get('/admin/document-requests/create')->assertOk();
    }

    public function test_panel_user_full_wizard_flow_generates_and_downloads_a_document(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        // requires_review: false — this test covers the plain generate ->
        // download happy path; the review-gated flow is covered separately
        // below (test_download_is_blocked_until_approved_when_review_is_required).
        $precedent = $this->makeWillPrecedent(['questionnaire_fields' => $this->willQuestionnaireFields(), 'requires_review' => false]);

        $this->actingAs($staff);

        Livewire::test(CreateDocumentRequest::class)
            ->fillForm([
                'precedent_id' => $precedent->id,
                'answers' => $this->willAnswers(),
                'parties' => ['beneficiaries' => $this->willBeneficiaryRows()],
                'case_reference' => 'MATTER-001',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $documentRequest = DocumentRequest::where('requested_by', $staff->id)->firstOrFail();

        $this->assertSame('completed', $documentRequest->status);
        $this->assertSame('MATTER-001', $documentRequest->case_reference);
        $this->assertSame('Ashley Dewell', $documentRequest->answers['testator_name']);
        $this->assertSame(2, $documentRequest->parties()->where('group_key', 'beneficiaries')->count());

        // The requesting staff member can download the result.
        $this->actingAs($staff)
            ->get(route('document-requests.download', $documentRequest))
            ->assertOk();
    }

    public function test_specific_substitute_selection_persists_correctly(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        $precedent = $this->makeWillPrecedent(['questionnaire_fields' => $this->willQuestionnaireFields()]);

        $this->actingAs($staff);

        Livewire::test(CreateDocumentRequest::class)
            ->fillForm([
                'precedent_id' => $precedent->id,
                'answers' => $this->willAnswers(),
                'parties' => [
                    'beneficiaries' => [
                        ['name' => 'Alfred Smith', 'share' => 50, 'gender' => 'male', 'per_stirpes' => false],
                        ['name' => 'Bernadette Smith', 'share' => 50, 'gender' => 'female', 'per_stirpes' => false, '_substitute_ref' => 'Alfred Smith'],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $documentRequest = DocumentRequest::where('requested_by', $staff->id)->firstOrFail();
        $primary = $documentRequest->parties()->whereJsonContains('data->name', 'Alfred Smith')->firstOrFail();
        $secondary = $documentRequest->parties()->whereJsonContains('data->name', 'Bernadette Smith')->firstOrFail();

        $this->assertSame($primary->id, $secondary->substitute_party_id);
        // The UI-only selector key must never leak into persisted row data.
        $this->assertArrayNotHasKey('_substitute_ref', $secondary->data);
    }

    public function test_review_step_warns_when_testator_dob_implies_under_minimum_age(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        $precedent = $this->makeWillPrecedent([
            'questionnaire_fields' => array_merge($this->willQuestionnaireFields(), [
                ['name' => 'testator_dob', 'label' => 'DOB', 'type' => 'date', 'required' => false, 'description' => ''],
            ]),
        ]);

        $this->actingAs($staff);

        Livewire::test(CreateDocumentRequest::class)
            ->fillForm([
                'precedent_id' => $precedent->id,
                'answers' => $this->willAnswers(['testator_dob' => now()->subYears(10)->toDateString()]),
                'parties' => ['beneficiaries' => $this->willBeneficiaryRows()],
            ])
            ->assertSee('below the standard minimum age of 18');
    }

    public function test_review_step_shows_no_warning_for_an_adult_testator(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        $precedent = $this->makeWillPrecedent([
            'questionnaire_fields' => array_merge($this->willQuestionnaireFields(), [
                ['name' => 'testator_dob', 'label' => 'DOB', 'type' => 'date', 'required' => false, 'description' => ''],
            ]),
        ]);

        $this->actingAs($staff);

        Livewire::test(CreateDocumentRequest::class)
            ->fillForm([
                'precedent_id' => $precedent->id,
                'answers' => $this->willAnswers(['testator_dob' => now()->subYears(40)->toDateString()]),
                'parties' => ['beneficiaries' => $this->willBeneficiaryRows()],
            ])
            ->assertDontSee('below the standard minimum age');
    }

    public function test_download_is_blocked_until_approved_when_review_is_required(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        $precedent = $this->makeWillPrecedent(['questionnaire_fields' => $this->willQuestionnaireFields()]); // requires_review defaults true

        $this->actingAs($staff);

        Livewire::test(CreateDocumentRequest::class)
            ->fillForm([
                'precedent_id' => $precedent->id,
                'answers' => $this->willAnswers(),
                'parties' => ['beneficiaries' => $this->willBeneficiaryRows()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $documentRequest = DocumentRequest::where('requested_by', $staff->id)->firstOrFail();
        $this->assertSame('completed', $documentRequest->status);
        $this->assertTrue($documentRequest->requiresApproval());
        $this->assertFalse($documentRequest->isApproved());

        // Not yet approved — even the requester who generated it can't download it.
        $this->actingAs($staff)
            ->get(route('document-requests.download', $documentRequest))
            ->assertNotFound();

        // A panel_user has no authority to approve — the resource-level
        // permission (update_document::request) isn't synced to that role.
        $this->assertFalse(Gate::forUser($staff)->allows('approve', $documentRequest));

        $operator = User::factory()->create();
        $operator->assignRole('operator');
        $this->assertTrue(Gate::forUser($operator)->allows('approve', $documentRequest));

        $documentRequest->update(['approved_at' => now(), 'approved_by' => $operator->id]);
        $documentRequest->refresh();

        $this->assertTrue($documentRequest->isApproved());
        $this->assertTrue($documentRequest->isReadyForDownload());

        $this->actingAs($staff)
            ->get(route('document-requests.download', $documentRequest))
            ->assertOk();
    }

    public function test_approve_action_visible_to_operator_but_not_panel_user(): void
    {
        $precedent = $this->makeWillPrecedent();
        $requester = User::factory()->create();
        $requester->assignRole('panel_user');
        $documentRequest = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by' => $requester->id,
            'answers' => [],
            'status' => 'completed',
            'generated_docx_path' => 'generated/does-not-matter.docx',
        ]);

        $operator = User::factory()->create();
        $operator->assignRole('operator');

        $this->actingAs($operator);
        Livewire::test(ViewDocumentRequest::class, ['record' => $documentRequest->id])
            ->assertActionVisible('approve');

        $this->actingAs($requester);
        Livewire::test(ViewDocumentRequest::class, ['record' => $documentRequest->id])
            ->assertActionHidden('approve');
    }

    public function test_download_route_404s_for_a_request_that_never_completed(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        $precedent = $this->makeWillPrecedent();

        $pending = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by' => $staff->id,
            'answers' => [],
            'status' => 'pending',
        ]);

        $this->actingAs($staff)
            ->get(route('document-requests.download', $pending))
            ->assertNotFound();
    }
}
