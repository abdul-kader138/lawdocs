<?php

namespace Tests\Feature;

use App\Filament\Resources\PrecedentResource\Pages\EditPrecedent;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Support\WillPrecedentFixture;
use Tests\TestCase;

class PrecedentQuestionnaireFieldsCsvImportTest extends TestCase
{
    use RefreshDatabase;
    use WillPrecedentFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShieldSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('fields.csv', $content);
    }

    public function test_valid_rows_are_imported_and_appended(): void
    {
        $precedent = $this->makeWillPrecedent(['questionnaire_fields' => []]);

        $csv = $this->csv(
            "name,label,type,required,description,options\n"
            ."testator_name,Testator's Full Name,text,true,The full legal name of the testator,\n"
            ."testator_gender,Gender,select,yes,The testator's gender,male=Male|female=Female\n"
        );

        Livewire::test(EditPrecedent::class, ['record' => $precedent->id])
            ->callAction('importFieldsCsv', data: ['csv' => $csv]);

        $precedent->refresh();
        $fields = collect($precedent->questionnaire_fields)->keyBy('name');

        $this->assertCount(2, $fields);
        $this->assertSame('text', $fields['testator_name']['type']);
        $this->assertTrue($fields['testator_name']['required']);
        $this->assertSame(['male' => 'Male', 'female' => 'Female'], $fields['testator_gender']['options']);
    }

    public function test_invalid_rows_are_skipped_with_valid_rows_still_imported(): void
    {
        $precedent = $this->makeWillPrecedent(['questionnaire_fields' => []]);

        $csv = $this->csv(
            "name,label,type,required,description,options\n"
            ."Bad Name!,Bad,text,true,Invalid name format,\n" // fails the name regex
            ."valid_field,Valid Field,not_a_real_type,true,Bad type,\n" // fails the type enum
            ."good_field,Good Field,text,true,A perfectly fine field,\n"
        );

        Livewire::test(EditPrecedent::class, ['record' => $precedent->id])
            ->callAction('importFieldsCsv', data: ['csv' => $csv]);

        $precedent->refresh();
        $fields = collect($precedent->questionnaire_fields)->keyBy('name');

        $this->assertCount(1, $fields, 'Only the one valid row should have been imported.');
        $this->assertTrue($fields->has('good_field'));
    }

    public function test_duplicate_name_within_one_csv_last_row_wins(): void
    {
        $precedent = $this->makeWillPrecedent(['questionnaire_fields' => []]);

        $csv = $this->csv(
            "name,label,type,required,description,options\n"
            ."client_name,First Label,text,true,First description,\n"
            ."client_name,Second Label,text,false,Second description,\n"
        );

        Livewire::test(EditPrecedent::class, ['record' => $precedent->id])
            ->callAction('importFieldsCsv', data: ['csv' => $csv]);

        $precedent->refresh();
        $fields = collect($precedent->questionnaire_fields)->keyBy('name');

        $this->assertCount(1, $fields);
        $this->assertSame('Second Label', $fields['client_name']['label']);
        $this->assertFalse($fields['client_name']['required']);
    }

    public function test_row_matching_an_existing_field_name_updates_it_in_place(): void
    {
        $precedent = $this->makeWillPrecedent([
            'questionnaire_fields' => [
                ['name' => 'testator_name', 'label' => 'Old Label', 'type' => 'text', 'required' => false, 'description' => 'Old description', 'options' => []],
                ['name' => 'other_field', 'label' => 'Other', 'type' => 'text', 'required' => true, 'description' => 'Untouched', 'options' => []],
            ],
        ]);

        $csv = $this->csv(
            "name,label,type,required,description,options\n"
            ."testator_name,New Label,text,true,New description,\n"
        );

        Livewire::test(EditPrecedent::class, ['record' => $precedent->id])
            ->callAction('importFieldsCsv', data: ['csv' => $csv]);

        $precedent->refresh();
        $fields = collect($precedent->questionnaire_fields)->keyBy('name');

        $this->assertCount(2, $fields, 'The existing unrelated field must survive the import untouched.');
        $this->assertSame('New Label', $fields['testator_name']['label']);
        $this->assertSame('Other', $fields['other_field']['label']);
    }
}
