<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentRequestResource\Pages\ViewDocumentRequest;
use App\Filament\Resources\DocumentRequestResource\RelationManagers\WitnessesRelationManager;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestWitness;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\WillPrecedentFixture;
use Tests\TestCase;

class DocumentRequestWitnessTest extends TestCase
{
    use RefreshDatabase;
    use WillPrecedentFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShieldSeeder::class);
    }

    private function makeDocumentRequestWithBeneficiary(string $beneficiaryName = 'Bernadette Smith'): DocumentRequest
    {
        $requester = User::factory()->create();
        $requester->assignRole('operator');
        $precedent = $this->makeWillPrecedent();

        $documentRequest = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by' => $requester->id,
            'answers' => [],
            'status' => 'pending',
        ]);

        $documentRequest->parties()->create([
            'group_key' => 'beneficiaries',
            'position' => 0,
            'data' => ['name' => $beneficiaryName, 'share' => 100, 'gender' => 'female'],
        ]);

        $this->actingAs($requester);

        return $documentRequest;
    }

    public function test_can_add_a_witness(): void
    {
        $documentRequest = $this->makeDocumentRequestWithBeneficiary();

        Livewire::test(WitnessesRelationManager::class, [
            'ownerRecord' => $documentRequest,
            'pageClass' => ViewDocumentRequest::class,
        ])
            ->callTableAction('create', data: [
                'name' => 'Chris Independent',
                'address' => '10 Tenth St, Sydney',
                'occupation' => 'Accountant',
            ]);

        $this->assertSame(1, $documentRequest->witnesses()->count());
        $this->assertSame('Chris Independent', $documentRequest->witnesses()->first()->name);
    }

    public function test_witness_cannot_share_a_name_with_a_party_on_the_same_request(): void
    {
        $documentRequest = $this->makeDocumentRequestWithBeneficiary('Bernadette Smith');

        Livewire::test(WitnessesRelationManager::class, [
            'ownerRecord' => $documentRequest,
            'pageClass' => ViewDocumentRequest::class,
        ])
            ->callTableAction('create', data: ['name' => 'Bernadette Smith'])
            ->assertHasTableActionErrors(['name']);

        $this->assertSame(0, $documentRequest->witnesses()->count());
    }

    public function test_witness_name_matching_is_case_insensitive(): void
    {
        $documentRequest = $this->makeDocumentRequestWithBeneficiary('Bernadette Smith');

        Livewire::test(WitnessesRelationManager::class, [
            'ownerRecord' => $documentRequest,
            'pageClass' => ViewDocumentRequest::class,
        ])
            ->callTableAction('create', data: ['name' => 'BERNADETTE smith'])
            ->assertHasTableActionErrors(['name']);
    }

    public function test_witnesses_are_scoped_to_their_own_document_request(): void
    {
        $documentRequestA = $this->makeDocumentRequestWithBeneficiary('Alfred Smith');
        $documentRequestB = $this->makeDocumentRequestWithBeneficiary('Bernadette Smith');

        $documentRequestA->witnesses()->create(['name' => 'Witness One', 'position' => 0]);
        $documentRequestB->witnesses()->create(['name' => 'Witness Two', 'position' => 0]);

        $this->assertSame(1, $documentRequestA->witnesses()->count());
        $this->assertSame(1, $documentRequestB->witnesses()->count());
    }

    public function test_deleting_document_request_cascades_to_witnesses(): void
    {
        $documentRequest = $this->makeDocumentRequestWithBeneficiary();
        $documentRequest->witnesses()->create(['name' => 'Witness One', 'position' => 0]);

        $documentRequest->delete();

        $this->assertSame(0, DocumentRequestWitness::count());
    }
}
