<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource\Pages\CreateClient;
use App\Filament\Resources\ClientResource\Pages\EditClient;
use App\Filament\Resources\ClientResource\RelationManagers\ContactsRelationManager;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShieldSeeder::class);
    }

    public function test_super_admin_can_access_client_resource(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->actingAs($admin)->get('/admin/clients')->assertOk();
        $this->actingAs($admin)->get('/admin/clients/create')->assertOk();
    }

    public function test_operator_can_access_client_resource(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('operator');

        $this->actingAs($operator)->get('/admin/clients')->assertOk();
    }

    public function test_panel_user_can_create_and_view_but_not_delete_clients(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');

        $this->actingAs($staff)->get('/admin/clients')->assertOk();
        $this->actingAs($staff)->get('/admin/clients/create')->assertOk();

        $this->assertTrue($staff->can('create_client'));
        $this->assertTrue($staff->can('update_client'));
        $this->assertFalse($staff->can('delete_client'));
    }

    public function test_creating_a_client_records_the_creator(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        $this->actingAs($staff);

        Livewire::test(CreateClient::class)
            ->fillForm([
                'name' => 'Ashley Dewell',
                'email' => 'ashley@example.com',
                'street' => '1 First Street',
                'suburb' => 'Sydney',
                'state' => 'NSW',
                'postcode' => '2000',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $client = Client::where('name', 'Ashley Dewell')->firstOrFail();
        $this->assertSame($staff->id, $client->created_by);
        $this->assertSame('Sydney', $client->suburb);
    }

    public function test_can_add_a_contact_to_a_client_via_the_relation_manager(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $client = Client::create(['name' => 'Ashley Dewell']);
        $this->actingAs($admin);

        Livewire::test(ContactsRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => EditClient::class,
        ])
            ->callTableAction('create', data: [
                'name' => 'Bernadette Smith',
                'relationship' => 'Spouse',
                'gender' => 'female',
            ]);

        $this->assertSame(1, $client->contacts()->count());
        $this->assertSame('Bernadette Smith', $client->contacts()->first()->name);
    }
}
