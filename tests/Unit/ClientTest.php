<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    private function makeTestPrecedentDocx(): string
    {
        Storage::fake('local');
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('placeholder');
        $tmp = tempnam(sys_get_temp_dir(), 'test_precedent_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        Storage::disk('local')->put('precedents/x.docx', file_get_contents($tmp));
        @unlink($tmp);

        return 'precedents/x.docx';
    }

    private function makePrecedent(): Precedent
    {
        return Precedent::create([
            'title' => 'Test', 'docx_path' => $this->makeTestPrecedentDocx(),
            'questionnaire_fields' => [], 'is_active' => true,
        ]);
    }

    public function test_to_prefill_attributes_shape(): void
    {
        $client = Client::create([
            'name' => 'Ashley Dewell',
            'email' => 'ashley@example.com',
            'phone' => '0400000000',
            'street' => '1 First Street',
            'suburb' => 'Sydney',
            'state' => 'NSW',
            'postcode' => '2000',
            'dob' => '1980-05-01',
            'gender' => 'female',
        ]);

        $attributes = $client->toPrefillAttributes();

        $this->assertSame('Ashley Dewell', $attributes['name']);
        $this->assertSame('1980-05-01', $attributes['dob']);
        $this->assertSame('NSW', $attributes['state']);
        $this->assertSame('female', $attributes['gender']);
    }

    public function test_contacts_relation_and_cascade_delete(): void
    {
        $client = Client::create(['name' => 'Ashley Dewell']);
        $client->contacts()->create(['name' => 'Bernadette Smith', 'relationship' => 'Spouse']);

        $this->assertCount(1, $client->contacts);

        $client->delete();

        $this->assertSame(0, ClientContact::count());
    }

    public function test_document_requests_relation(): void
    {
        $client = Client::create(['name' => 'Ashley Dewell']);
        $precedent = $this->makePrecedent();
        $user = User::factory()->create();

        DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'client_id' => $client->id,
            'requested_by' => $user->id,
            'answers' => [],
            'status' => 'pending',
        ]);

        $this->assertCount(1, $client->documentRequests);
    }

    public function test_deleting_client_nulls_document_request_client_id_not_cascades(): void
    {
        $client = Client::create(['name' => 'Ashley Dewell']);
        $precedent = $this->makePrecedent();
        $user = User::factory()->create();

        $documentRequest = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'client_id' => $client->id,
            'requested_by' => $user->id,
            'answers' => [],
            'status' => 'pending',
        ]);

        $client->delete();
        $documentRequest->refresh();

        $this->assertNull($documentRequest->client_id);
        $this->assertNotNull(DocumentRequest::find($documentRequest->id));
    }
}
