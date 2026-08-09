<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentRequestResource\Pages\CreateDocumentRequest;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->seed(\Database\Seeders\ShieldSeeder::class);
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
            ['name' => 'beneficiaries', 'label' => 'Beneficiaries', 'type' => 'textarea', 'required' => true, 'description' => ''],
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
        $precedent = $this->makeWillPrecedent(['questionnaire_fields' => $this->willQuestionnaireFields()]);

        $this->actingAs($staff);

        Livewire::test(CreateDocumentRequest::class)
            ->fillForm(['precedent_id' => $precedent->id, 'answers' => $this->willAnswers(), 'case_reference' => 'MATTER-001'])
            ->call('create')
            ->assertHasNoFormErrors();

        $documentRequest = DocumentRequest::where('requested_by', $staff->id)->firstOrFail();

        $this->assertSame('completed', $documentRequest->status);
        $this->assertSame('MATTER-001', $documentRequest->case_reference);
        $this->assertSame('Ashley Dewell', $documentRequest->answers['testator_name']);

        // The requesting staff member can download the result.
        $this->actingAs($staff)
            ->get(route('document-requests.download', $documentRequest))
            ->assertOk();
    }

    public function test_download_route_404s_for_a_request_that_never_completed(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        $precedent = $this->makeWillPrecedent();

        $pending = DocumentRequest::create([
            'precedent_id'             => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by'             => $staff->id,
            'answers'                  => [],
            'status'                   => 'pending',
        ]);

        $this->actingAs($staff)
            ->get(route('document-requests.download', $pending))
            ->assertNotFound();
    }
}
