<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentRequestResource\Pages\CreateDocumentRequest;
use App\Filament\Resources\DocumentRequestResource\Pages\ListDocumentRequests;
use App\Models\Client;
use App\Models\DocumentRequest;
use App\Models\User;
use App\Support\PartyGroupFormBuilder;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\WillPrecedentFixture;
use Tests\TestCase;

class ClientPrefillWizardTest extends TestCase
{
    use RefreshDatabase;
    use WillPrecedentFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShieldSeeder::class);
    }

    private function clientFieldMap(): array
    {
        return [
            'name' => 'testator_name',
            'dob' => 'testator_dob',
            'street' => 'testator_street',
            'suburb' => 'testator_suburb',
            'state' => 'testator_state',
            'gender' => 'testator_gender',
        ];
    }

    public function test_selecting_a_client_prefills_mapped_answer_fields(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        $precedent = $this->makeWillPrecedent(['client_field_map' => $this->clientFieldMap()]);
        $client = Client::create([
            'name' => 'Ashley Dewell',
            'dob' => '1980-05-01',
            'street' => '1 First Street',
            'suburb' => 'Sydney',
            'state' => 'NSW',
            'gender' => 'female',
        ]);

        $this->actingAs($staff);

        Livewire::test(CreateDocumentRequest::class)
            ->fillForm(['precedent_id' => $precedent->id, 'client_id' => $client->id])
            ->assertSet('data.answers.testator_name', 'Ashley Dewell')
            ->assertSet('data.answers.testator_street', '1 First Street')
            ->assertSet('data.answers.testator_suburb', 'Sydney')
            ->assertSet('data.answers.testator_gender', 'female');
    }

    public function test_unmapped_client_attributes_are_left_alone(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        // Map only "name" — dob/street/etc are deliberately left unmapped.
        $precedent = $this->makeWillPrecedent(['client_field_map' => ['name' => 'testator_name']]);
        $client = Client::create(['name' => 'Ashley Dewell', 'suburb' => 'Sydney']);

        $this->actingAs($staff);

        Livewire::test(CreateDocumentRequest::class)
            ->fillForm(['precedent_id' => $precedent->id, 'client_id' => $client->id])
            ->assertSet('data.answers.testator_name', 'Ashley Dewell')
            ->assertSet('data.answers.testator_suburb', null);
    }

    public function test_submitting_with_a_client_selected_links_the_document_request(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        $precedent = $this->makeWillPrecedent(['client_field_map' => $this->clientFieldMap()]);
        $client = Client::create(['name' => 'Ashley Dewell']);

        $this->actingAs($staff);

        Livewire::test(CreateDocumentRequest::class)
            ->fillForm([
                'client_id' => $client->id,
                'precedent_id' => $precedent->id,
                'answers' => $this->willAnswers(),
                'parties' => ['beneficiaries' => $this->willBeneficiaryRows()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $documentRequest = DocumentRequest::where('requested_by', $staff->id)->firstOrFail();
        $this->assertSame($client->id, $documentRequest->client_id);
    }

    public function test_importing_a_contact_appends_a_prefilled_party_row(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        $precedent = $this->makeWillPrecedent();
        $client = Client::create(['name' => 'Ashley Dewell']);
        $contact = $client->contacts()->create([
            'name' => 'Bernadette Smith',
            'gender' => 'female',
        ]);

        $this->actingAs($staff);

        Livewire::test(CreateDocumentRequest::class)
            ->fillForm(['precedent_id' => $precedent->id, 'client_id' => $client->id])
            ->fillForm([PartyGroupFormBuilder::IMPORT_CONTACTS_FIELD_PREFIX.'beneficiaries' => [$contact->id]])
            ->assertSet('data.parties.beneficiaries.0.name', 'Bernadette Smith')
            ->assertSet('data.parties.beneficiaries.0.gender', 'female');
    }

    public function test_importing_two_contacts_appends_two_rows(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        $precedent = $this->makeWillPrecedent();
        $client = Client::create(['name' => 'Ashley Dewell']);
        $first = $client->contacts()->create(['name' => 'Bernadette Smith', 'gender' => 'female']);
        $second = $client->contacts()->create(['name' => 'Charlie Smith', 'gender' => 'male']);

        $this->actingAs($staff);

        $test = Livewire::test(CreateDocumentRequest::class)
            ->fillForm(['precedent_id' => $precedent->id, 'client_id' => $client->id])
            ->fillForm([PartyGroupFormBuilder::IMPORT_CONTACTS_FIELD_PREFIX.'beneficiaries' => [$first->id, $second->id]]);

        $rows = $test->get('data.parties.beneficiaries');
        $this->assertCount(2, $rows);
        $this->assertSame(['Bernadette Smith', 'Charlie Smith'], array_column($rows, 'name'));
    }

    public function test_no_import_field_shown_when_client_has_no_contacts(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        $precedent = $this->makeWillPrecedent();
        $client = Client::create(['name' => 'Ashley Dewell']);

        $this->actingAs($staff);

        Livewire::test(CreateDocumentRequest::class)
            ->fillForm(['precedent_id' => $precedent->id, 'client_id' => $client->id])
            ->assertFormFieldDoesNotExist(PartyGroupFormBuilder::IMPORT_CONTACTS_FIELD_PREFIX.'beneficiaries');
    }

    public function test_document_request_list_shows_and_filters_by_client(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        $precedent = $this->makeWillPrecedent();
        $clientA = Client::create(['name' => 'Ashley Dewell']);
        $clientB = Client::create(['name' => 'Jordan Rivers']);

        $requestA = DocumentRequest::create([
            'precedent_id' => $precedent->id, 'precedent_title_snapshot' => $precedent->title,
            'client_id' => $clientA->id, 'requested_by' => $staff->id, 'answers' => [], 'status' => 'pending',
        ]);
        $requestB = DocumentRequest::create([
            'precedent_id' => $precedent->id, 'precedent_title_snapshot' => $precedent->title,
            'client_id' => $clientB->id, 'requested_by' => $staff->id, 'answers' => [], 'status' => 'pending',
        ]);

        $this->actingAs($staff);

        Livewire::test(ListDocumentRequests::class)
            ->assertCanSeeTableRecords([$requestA, $requestB])
            ->filterTable('client_id', $clientA->id)
            ->assertCanSeeTableRecords([$requestA])
            ->assertCanNotSeeTableRecords([$requestB]);
    }
}
