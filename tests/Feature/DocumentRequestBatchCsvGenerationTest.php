<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentRequestResource\Pages\ListDocumentRequests;
use App\Models\Client;
use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Models\User;
use App\Support\BatchCsvRowValidator;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

/**
 * Deliberately uses its own minimal precedent rather than WillPrecedentFixture:
 * that shared fixture's beneficiaries_clause contains an unconditional
 * [[IF:beneficiary.per_stirpes]] marker, and per_stirpes is explicitly out of
 * scope for CSV batch import (see BatchCsvRowValidator's docblock) — a batch
 * row never supplies it, so ConditionEvaluator would throw an unknown-flag
 * error for every row against that particular fixture. This precedent's
 * beneficiaries_clause has no such conditional, so it's actually batchable.
 */
class DocumentRequestBatchCsvGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShieldSeeder::class);
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        return $admin;
    }

    private function makeBatchTestPrecedent(array $overrides = []): Precedent
    {
        Storage::fake('local');

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('[[CLAUSE:front_matter]]');
        $section->addText('This is the last will and testament of {{answers.testator_name}}.');
        $section->addText('[[/CLAUSE]]');
        $section->addText('[[CLAUSE:revocation]]');
        $section->addText('I revoke all prior wills and testamentary acts.');
        $section->addText('[[/CLAUSE]]');
        $section->addText('[[CLAUSE:executor_powers]]');
        $section->addText('My executor has full powers to administer my estate.');
        $section->addText('[[/CLAUSE]]');
        $section->addText('[[CLAUSE:beneficiaries_clause]]');
        $section->addText('[[REPEAT:beneficiaries AS beneficiary]]');
        $section->addText('I give {{beneficiary.share}}% of my estate to {{beneficiary.name}}.');
        $section->addText('[[/REPEAT]]');
        $section->addText('[[/CLAUSE]]');

        $tmp = tempnam(sys_get_temp_dir(), 'batch_precedent_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        Storage::disk('local')->put('precedents/batch.docx', file_get_contents($tmp));
        @unlink($tmp);

        return Precedent::create(array_merge([
            'title' => 'Batch Test Will',
            'category' => 'will',
            'docx_path' => 'precedents/batch.docx',
            'generator_class' => 'will',
            'is_active' => true,
            'jurisdiction' => 'NSW',
            'questionnaire_fields' => [
                ['name' => 'testator_name', 'label' => 'Testator Name', 'type' => 'text', 'required' => true],
                ['name' => 'executor_name', 'label' => 'Executor Name', 'type' => 'text', 'required' => true],
                ['name' => 'executor_gender', 'label' => 'Executor Gender', 'type' => 'select', 'required' => true, 'options' => ['male' => 'Male', 'female' => 'Female']],
                ['name' => 'signing_date', 'label' => 'Signing Date', 'type' => 'date', 'required' => false],
            ],
            'party_groups' => [
                [
                    'key' => 'beneficiaries',
                    'label' => 'Beneficiaries',
                    'min_items' => 1,
                    'max_items' => 3,
                    'share_field' => null,
                    'fields' => [
                        ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                        ['name' => 'share', 'label' => 'Share', 'type' => 'number', 'required' => true],
                    ],
                ],
            ],
        ], $overrides));
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('batch.csv', $content);
    }

    public function test_valid_multi_row_csv_creates_documents_with_correct_data(): void
    {
        $this->actingAsAdmin();
        $precedent = $this->makeBatchTestPrecedent();

        $csv = $this->csv(
            "case_reference,testator_name,executor_name,executor_gender,signing_date,beneficiaries.1.name,beneficiaries.1.share,beneficiaries.2.name,beneficiaries.2.share\n"
            ."MATTER-001,Ashley Dewell,Alfred Smith,male,2026-08-14,Bob Beneficiary,100,,\n"
            ."MATTER-002,Jane Doe,John Executor,male,,Carol One,50,Dave Two,50\n"
        );

        Livewire::test(ListDocumentRequests::class)
            ->callAction('batchGenerateCsv', data: ['precedent_id' => $precedent->id, 'csv' => $csv]);

        $this->assertSame(2, DocumentRequest::count());

        $first = DocumentRequest::where('case_reference', 'MATTER-001')->firstOrFail();
        $this->assertSame('completed', $first->status);
        $this->assertSame('Ashley Dewell', $first->answers['testator_name']);
        $this->assertSame('2026-08-14', $first->answers['signing_date']);
        $this->assertCount(1, $first->parties);

        $second = DocumentRequest::where('case_reference', 'MATTER-002')->firstOrFail();
        $this->assertSame('completed', $second->status);
        $this->assertArrayNotHasKey('signing_date', $second->answers, 'A blank optional field must be omitted, not written as null/empty.');
        $this->assertCount(2, $second->parties);
    }

    public function test_row_missing_required_field_is_skipped_but_others_still_generate(): void
    {
        $this->actingAsAdmin();
        $precedent = $this->makeBatchTestPrecedent();

        $csv = $this->csv(
            "case_reference,testator_name,executor_name,executor_gender,beneficiaries.1.name,beneficiaries.1.share\n"
            .",,John Executor,male,Bob,100\n" // missing testator_name
            ."MATTER-002,Jane Doe,John Executor,male,Bob,100\n"
        );

        Livewire::test(ListDocumentRequests::class)
            ->callAction('batchGenerateCsv', data: ['precedent_id' => $precedent->id, 'csv' => $csv]);

        $this->assertSame(1, DocumentRequest::count());
        $this->assertSame('MATTER-002', DocumentRequest::first()->case_reference);
    }

    public function test_row_violating_min_items_is_skipped(): void
    {
        $this->actingAsAdmin();
        $precedent = $this->makeBatchTestPrecedent();

        $csv = $this->csv(
            "case_reference,testator_name,executor_name,executor_gender,beneficiaries.1.name,beneficiaries.1.share\n"
            ."MATTER-001,Ashley Dewell,Alfred Smith,male,,\n" // no beneficiaries at all, min_items=1
        );

        Livewire::test(ListDocumentRequests::class)
            ->callAction('batchGenerateCsv', data: ['precedent_id' => $precedent->id, 'csv' => $csv]);

        $this->assertSame(0, DocumentRequest::count());
    }

    public function test_unknown_header_column_rejects_whole_file(): void
    {
        $this->actingAsAdmin();
        $precedent = $this->makeBatchTestPrecedent();

        $csv = $this->csv(
            "case_reference,testator_name,executor_name,executor_gender,not_a_real_column,beneficiaries.1.name,beneficiaries.1.share\n"
            ."MATTER-001,Ashley Dewell,Alfred Smith,male,oops,Bob,100\n"
        );

        Livewire::test(ListDocumentRequests::class)
            ->callAction('batchGenerateCsv', data: ['precedent_id' => $precedent->id, 'csv' => $csv]);

        $this->assertSame(0, DocumentRequest::count(), 'A malformed header must reject the whole file, not just the bad column.');
    }

    public function test_client_id_column_valid_and_invalid(): void
    {
        $this->actingAsAdmin();
        $precedent = $this->makeBatchTestPrecedent();
        $client = Client::create(['name' => 'Existing Client']);

        $csv = $this->csv(
            "case_reference,client_id,testator_name,executor_name,executor_gender,beneficiaries.1.name,beneficiaries.1.share\n"
            ."MATTER-001,{$client->id},Ashley Dewell,Alfred Smith,male,Bob,100\n"
            ."MATTER-002,999999,Jane Doe,John Executor,male,Bob,100\n" // no such client
        );

        Livewire::test(ListDocumentRequests::class)
            ->callAction('batchGenerateCsv', data: ['precedent_id' => $precedent->id, 'csv' => $csv]);

        $this->assertSame(1, DocumentRequest::count());
        $this->assertSame($client->id, DocumentRequest::first()->client_id);
    }

    public function test_row_cap_rejects_oversized_file(): void
    {
        $this->actingAsAdmin();
        $precedent = $this->makeBatchTestPrecedent();

        $lines = ['testator_name'];
        for ($i = 0; $i < BatchCsvRowValidator::MAX_ROWS + 1; $i++) {
            $lines[] = 'Test';
        }
        $csv = $this->csv(implode("\n", $lines)."\n");

        Livewire::test(ListDocumentRequests::class)
            ->callAction('batchGenerateCsv', data: ['precedent_id' => $precedent->id, 'csv' => $csv]);

        $this->assertSame(0, DocumentRequest::count());
    }

    public function test_unauthorized_user_cannot_see_batch_generate_action(): void
    {
        // Can view the list (so the page itself mounts) but lacks
        // create_document::request — no built-in role has view without
        // create, so grant the one permission directly.
        $user = User::factory()->create();
        $user->givePermissionTo('view_any_document::request');
        $this->actingAs($user);

        Livewire::test(ListDocumentRequests::class)
            ->assertActionHidden('batchGenerateCsv');
    }

    public function test_downloaded_template_round_trips_through_batch_generate(): void
    {
        $this->actingAsAdmin();
        $precedent = $this->makeBatchTestPrecedent();

        Livewire::test(ListDocumentRequests::class)
            ->callAction('downloadBatchCsvTemplate', data: ['precedent_id' => $precedent->id])
            ->assertFileDownloaded();

        $header = BatchCsvRowValidator::buildTemplateHeader($precedent);
        $values = collect($header)->map(fn ($col) => match (true) {
            $col === 'case_reference' => 'MATTER-TEMPLATE',
            $col === 'client_id' => '',
            $col === 'testator_name' => 'Ashley Dewell',
            $col === 'executor_name' => 'Alfred Smith',
            $col === 'executor_gender' => 'male',
            $col === 'signing_date' => '',
            str_ends_with($col, '.name') => 'Bob Beneficiary',
            str_ends_with($col, '.share') => '100',
            default => '',
        })->all();

        $csv = $this->csv(implode(',', $header)."\n".implode(',', $values)."\n");

        Livewire::test(ListDocumentRequests::class)
            ->callAction('batchGenerateCsv', data: ['precedent_id' => $precedent->id, 'csv' => $csv]);

        $this->assertSame(1, DocumentRequest::count());
        $this->assertSame('completed', DocumentRequest::first()->status);
    }
}
