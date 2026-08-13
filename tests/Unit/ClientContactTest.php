<?php

namespace Tests\Unit;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_importable_attributes_includes_a_combined_address(): void
    {
        $client = Client::create(['name' => 'Ashley Dewell']);
        $contact = $client->contacts()->create([
            'name' => 'Bernadette Smith',
            'relationship' => 'Spouse',
            'gender' => 'female',
            'street' => '2 Second Street',
            'suburb' => 'Sydney',
            'state' => 'NSW',
            'postcode' => '2000',
        ]);

        $attributes = $contact->toImportableAttributes();

        $this->assertSame('Bernadette Smith', $attributes['name']);
        $this->assertSame('Spouse', $attributes['relationship']);
        $this->assertSame('female', $attributes['gender']);
        $this->assertSame('2 Second Street, Sydney, NSW, 2000', $attributes['address']);
        $this->assertSame('Sydney', $attributes['suburb']);
    }

    public function test_to_importable_attributes_address_is_null_when_no_address_fields_set(): void
    {
        $client = Client::create(['name' => 'Ashley Dewell']);
        $contact = $client->contacts()->create(['name' => 'Bernadette Smith']);

        $this->assertNull($contact->toImportableAttributes()['address']);
    }

    public function test_belongs_to_client(): void
    {
        $client = Client::create(['name' => 'Ashley Dewell']);
        $contact = $client->contacts()->create(['name' => 'Bernadette Smith']);

        $this->assertTrue($contact->client->is($client));
    }
}
