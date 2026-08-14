<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentRequestResource\Pages\CreateDocumentRequest;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestParty;
use App\Models\User;
use App\Services\DocxToPdfConverter;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpWord\IOFactory;
use Tests\Support\WillPrecedentFixture;
use Tests\TestCase;

class DocumentRequestPreviewTest extends TestCase
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
            ['name' => 'testator_name', 'label' => "Testator's Name", 'type' => 'text', 'required' => true, 'description' => ''],
            ['name' => 'testator_street', 'label' => 'Street', 'type' => 'text', 'required' => true, 'description' => ''],
            ['name' => 'testator_suburb', 'label' => 'Suburb', 'type' => 'text', 'required' => true, 'description' => ''],
            ['name' => 'testator_state', 'label' => 'State', 'type' => 'text', 'required' => true, 'description' => ''],
            ['name' => 'testator_gender', 'label' => 'Gender', 'type' => 'select', 'required' => true, 'description' => '', 'options' => ['male' => 'Male', 'female' => 'Female']],
            ['name' => 'executor_name', 'label' => 'Executor Name', 'type' => 'text', 'required' => true, 'description' => ''],
            ['name' => 'executor_gender', 'label' => 'Executor Gender', 'type' => 'select', 'required' => true, 'description' => '', 'options' => ['male' => 'Male', 'female' => 'Female']],
        ];
    }

    private function makeStaff(): User
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');

        return $staff;
    }

    public function test_previewing_a_fully_filled_form_streams_a_valid_docx_without_persisting_anything(): void
    {
        $staff = $this->makeStaff();
        $precedent = $this->makeWillPrecedent(['questionnaire_fields' => $this->willQuestionnaireFields()]);

        $this->actingAs($staff);

        Livewire::test(CreateDocumentRequest::class)
            ->fillForm([
                'precedent_id' => $precedent->id,
                'answers' => $this->willAnswers(),
                'parties' => ['beneficiaries' => $this->willBeneficiaryRows()],
                'case_reference' => 'MATTER-001',
            ])
            ->callAction('previewDocx')
            ->assertFileDownloaded();

        // The whole point: nothing was left behind by the preview.
        $this->assertSame(0, DocumentRequest::count());
        $this->assertSame(0, DocumentRequestParty::count());
    }

    public function test_preview_works_with_deliberately_partial_answers_and_no_parties(): void
    {
        $staff = $this->makeStaff();
        $precedent = $this->makeWillPrecedent(['questionnaire_fields' => $this->willQuestionnaireFields()]);

        $this->actingAs($staff);

        // Only a subset of answers, no beneficiaries at all — this is the
        // "see it as you go" case a full-submit validation would reject.
        Livewire::test(CreateDocumentRequest::class)
            ->fillForm([
                'precedent_id' => $precedent->id,
                'answers' => ['testator_name' => 'Ashley Dewell'],
            ])
            ->callAction('previewDocx')
            ->assertFileDownloaded();

        $this->assertSame(0, DocumentRequest::count());
        $this->assertSame(0, DocumentRequestParty::count());
    }

    public function test_preview_without_a_precedent_selected_shows_a_notification_and_downloads_nothing(): void
    {
        $staff = $this->makeStaff();

        $this->actingAs($staff);

        Livewire::test(CreateDocumentRequest::class)
            ->callAction('previewDocx')
            ->assertNoFileDownloaded()
            ->assertNotified('Preview failed');

        $this->assertSame(0, DocumentRequest::count());
    }

    public function test_no_rows_survive_a_preview_even_when_the_generator_fails(): void
    {
        $staff = $this->makeStaff();
        $precedent = $this->makeWillPrecedent([
            'questionnaire_fields' => $this->willQuestionnaireFields(),
            'generator_class' => 'nonexistent_key',
        ]);

        $this->actingAs($staff);

        Livewire::test(CreateDocumentRequest::class)
            ->fillForm([
                'precedent_id' => $precedent->id,
                'answers' => $this->willAnswers(),
                'parties' => ['beneficiaries' => $this->willBeneficiaryRows()],
            ])
            ->callAction('previewDocx')
            ->assertNoFileDownloaded()
            ->assertNotified('Preview failed');

        $this->assertSame(0, DocumentRequest::count());
        $this->assertSame(0, DocumentRequestParty::count());
    }

    public function test_docx_preview_is_a_genuinely_valid_readable_document(): void
    {
        $staff = $this->makeStaff();
        $precedent = $this->makeWillPrecedent(['questionnaire_fields' => $this->willQuestionnaireFields()]);

        $this->actingAs($staff);

        $test = Livewire::test(CreateDocumentRequest::class)
            ->fillForm([
                'precedent_id' => $precedent->id,
                'answers' => $this->willAnswers(),
                'parties' => ['beneficiaries' => $this->willBeneficiaryRows()],
            ])
            ->callAction('previewDocx');

        $content = base64_decode(data_get($test->effects, 'download.content'));
        $tmp = tempnam(sys_get_temp_dir(), 'preview_check_').'.docx';
        file_put_contents($tmp, $content);

        $reloaded = IOFactory::load($tmp, 'Word2007');
        $this->assertCount(1, $reloaded->getSections());

        @unlink($tmp);
    }

    public function test_pdf_preview_streams_inline_when_libreoffice_available(): void
    {
        if (! app(DocxToPdfConverter::class)->isAvailable()) {
            $this->markTestSkipped('LibreOffice not installed in this environment.');
        }

        $staff = $this->makeStaff();
        $precedent = $this->makeWillPrecedent(['questionnaire_fields' => $this->willQuestionnaireFields()]);

        $this->actingAs($staff);

        $test = Livewire::test(CreateDocumentRequest::class)
            ->fillForm([
                'precedent_id' => $precedent->id,
                'answers' => $this->willAnswers(),
                'parties' => ['beneficiaries' => $this->willBeneficiaryRows()],
            ])
            ->callAction('previewPdf')
            ->assertFileDownloaded();

        $this->assertSame('application/pdf', data_get($test->effects, 'download.contentType'));
        $this->assertSame(0, DocumentRequest::count());
        $this->assertSame(0, DocumentRequestParty::count());
    }
}
