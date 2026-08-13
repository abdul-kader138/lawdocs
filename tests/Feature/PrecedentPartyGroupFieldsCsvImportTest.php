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

class PrecedentPartyGroupFieldsCsvImportTest extends TestCase
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

    /** Two empty groups ("beneficiaries" and "executors") with no row fields yet, to import into. */
    private function makePrecedentWithEmptyGroups(array $overrides = [])
    {
        return $this->makeWillPrecedent(array_merge([
            'party_groups' => [
                ['key' => 'beneficiaries', 'label' => 'Beneficiaries', 'role_type' => 'beneficiary', 'min_items' => 1, 'max_items' => null, 'share_field' => null, 'supports_substitute' => false, 'supports_per_stirpes' => false, 'fields' => []],
                ['key' => 'executors', 'label' => 'Executors', 'role_type' => 'executor', 'min_items' => 1, 'max_items' => null, 'share_field' => null, 'supports_substitute' => false, 'supports_per_stirpes' => false, 'fields' => []],
            ],
        ], $overrides));
    }

    public function test_valid_rows_are_imported_into_the_matching_group_only(): void
    {
        $precedent = $this->makePrecedentWithEmptyGroups();

        $csv = $this->csv(
            "group_key,name,label,type,required,description,options\n"
            ."beneficiaries,share,Share of Estate,number,true,The beneficiary's percentage share,\n"
            ."executors,relationship,Relationship to Testator,text,false,How the executor knows the testator,\n"
        );

        Livewire::test(EditPrecedent::class, ['record' => $precedent->id])
            ->callAction('importPartyGroupFieldsCsv', data: ['csv' => $csv]);

        $precedent->refresh();
        $groups = collect($precedent->party_groups)->keyBy('key');

        $beneficiaryFields = collect($groups['beneficiaries']['fields'])->keyBy('name');
        $executorFields = collect($groups['executors']['fields'])->keyBy('name');

        $this->assertCount(1, $beneficiaryFields);
        $this->assertTrue($beneficiaryFields->has('share'));
        $this->assertCount(1, $executorFields);
        $this->assertTrue($executorFields->has('relationship'));
    }

    public function test_row_with_unknown_group_key_is_skipped_with_a_reason(): void
    {
        $precedent = $this->makePrecedentWithEmptyGroups();

        $csv = $this->csv(
            "group_key,name,label,type,required,description,options\n"
            ."guardians,name,Name,text,true,Not a real group on this precedent,\n"
            ."beneficiaries,share,Share of Estate,number,true,The beneficiary's percentage share,\n"
        );

        Livewire::test(EditPrecedent::class, ['record' => $precedent->id])
            ->callAction('importPartyGroupFieldsCsv', data: ['csv' => $csv]);

        $precedent->refresh();
        $groups = collect($precedent->party_groups)->keyBy('key');

        $this->assertCount(1, $groups['beneficiaries']['fields']);
        $this->assertCount(0, $groups['executors']['fields']);
    }

    public function test_missing_group_key_column_skips_every_row(): void
    {
        $precedent = $this->makePrecedentWithEmptyGroups();

        $csv = $this->csv(
            "name,label,type,required,description,options\n"
            ."share,Share of Estate,number,true,The beneficiary's percentage share,\n"
        );

        Livewire::test(EditPrecedent::class, ['record' => $precedent->id])
            ->callAction('importPartyGroupFieldsCsv', data: ['csv' => $csv]);

        $precedent->refresh();
        $groups = collect($precedent->party_groups)->keyBy('key');

        $this->assertCount(0, $groups['beneficiaries']['fields']);
        $this->assertCount(0, $groups['executors']['fields']);
    }

    public function test_row_matching_an_existing_field_in_that_group_updates_it_in_place(): void
    {
        $precedent = $this->makePrecedentWithEmptyGroups([
            'party_groups' => [
                ['key' => 'beneficiaries', 'label' => 'Beneficiaries', 'role_type' => 'beneficiary', 'min_items' => 1, 'max_items' => null, 'share_field' => null, 'supports_substitute' => false, 'supports_per_stirpes' => false, 'fields' => [
                    ['name' => 'share', 'label' => 'Old Label', 'type' => 'number', 'required' => false, 'description' => 'Old description', 'options' => []],
                ]],
            ],
        ]);

        $csv = $this->csv(
            "group_key,name,label,type,required,description,options\n"
            ."beneficiaries,share,New Label,number,true,New description,\n"
        );

        Livewire::test(EditPrecedent::class, ['record' => $precedent->id])
            ->callAction('importPartyGroupFieldsCsv', data: ['csv' => $csv]);

        $precedent->refresh();
        $group = collect($precedent->party_groups)->firstWhere('key', 'beneficiaries');
        $fields = collect($group['fields'])->keyBy('name');

        $this->assertCount(1, $fields);
        $this->assertSame('New Label', $fields['share']['label']);
        $this->assertTrue($fields['share']['required']);
    }
}
