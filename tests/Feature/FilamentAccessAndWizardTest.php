<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentRequestResource\Pages\CreateDocumentRequest;
use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class FilamentAccessAndWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ShieldSeeder::class);
    }

    private function makePrecedent(): Precedent
    {
        Storage::fake('local');
        $phpWord = new PhpWord();
        $phpWord->addSection()->addText('placeholder');
        $tmp = tempnam(sys_get_temp_dir(), 'precedent_') . '.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        Storage::disk('local')->put('precedents/will.docx', file_get_contents($tmp));
        @unlink($tmp);

        return Precedent::create([
            'title'                => 'Last Will and Testament',
            'docx_path'            => 'precedents/will.docx',
            'questionnaire_fields' => [
                ['name' => 'testator_name', 'label' => "Testator's Name", 'type' => 'text', 'required' => true, 'description' => 'Full legal name'],
            ],
            'is_active' => true,
        ]);
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
        Setting::set('claude_api_key', 'sk-test', 'claude');

        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'stop_reason' => 'tool_use',
                'content'     => [[
                    'type'  => 'tool_use',
                    'name'  => 'draft_document',
                    'input' => [
                        'title'  => 'Last Will and Testament of John Doe',
                        'blocks' => [
                            ['type' => 'heading', 'level' => 2, 'text' => '1. Executor'],
                            ['type' => 'paragraph', 'text' => 'I appoint Jane Doe as Executor.'],
                        ],
                    ],
                ]],
            ]),
        ]);

        $staff     = User::factory()->create();
        $staff->assignRole('panel_user');
        $precedent = $this->makePrecedent();

        $this->actingAs($staff);

        Livewire::test(CreateDocumentRequest::class)
            ->fillForm([
                'precedent_id'          => $precedent->id,
                'answers.testator_name' => 'John Doe',
                'case_reference'        => 'MATTER-001',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $documentRequest = DocumentRequest::where('requested_by', $staff->id)->firstOrFail();

        $this->assertSame('completed', $documentRequest->status);
        $this->assertSame('MATTER-001', $documentRequest->case_reference);
        $this->assertSame('John Doe', $documentRequest->answers['testator_name']);

        // The requesting staff member can download the result.
        $this->actingAs($staff)
            ->get(route('document-requests.download', $documentRequest))
            ->assertOk();
    }

    public function test_download_route_404s_for_a_request_that_never_completed(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        $precedent = $this->makePrecedent();

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
