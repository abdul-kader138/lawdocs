<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'panel_user', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');

        $this->user      = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->user->assignRole('panel_user');
        $this->otherUser->assignRole('panel_user');
    }

    public function test_nested_path_with_encoded_slashes_round_trips_correctly(): void
    {
        $this->actingAs($this->user);

        $this->postJson("/panel-api/documents/{$this->user->id}/files/%2F", [
            'name' => 'Reports', 'type' => 'folder',
        ])->assertOk()->assertJson(['result' => ['id' => '/Reports']]);

        $this->postJson("/panel-api/documents/{$this->user->id}/files/%2FReports", [
            'name' => '2024', 'type' => 'folder',
        ])->assertOk()->assertJson(['result' => ['id' => '/Reports/2024']]);

        // encodeURIComponent("/Reports/2024") === "%2FReports%2F2024"
        $res = $this->getJson("/panel-api/documents/{$this->user->id}/files/%2FReports%2F2024");
        $res->assertOk()->assertJson([]);

        $root = $this->getJson("/panel-api/documents/{$this->user->id}/files")->json();
        $this->assertSame('/Reports', $root[0]['id']);
        $this->assertTrue($root[0]['lazy']);

        $children = $this->getJson("/panel-api/documents/{$this->user->id}/files/%2FReports")->json();
        $this->assertSame('/Reports/2024', $children[0]['id']);
    }

    public function test_upload_rename_move_copy_delete_lifecycle(): void
    {
        $this->actingAs($this->user);
        $ownerId = $this->user->id;

        $file = UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf');
        $upload = $this->post("/panel-api/documents/{$ownerId}/upload?id=%2F", [
            'file' => $file,
            'name' => 'invoice.pdf',
        ])->assertOk()->json();
        $this->assertSame('/invoice.pdf', $upload['result']['id']);

        // rename
        $this->putJson("/panel-api/documents/{$ownerId}/files/%2Finvoice.pdf", [
            'operation' => 'rename', 'name' => 'invoice-final.pdf',
        ])->assertOk()->assertJson(['result' => ['id' => '/invoice-final.pdf']]);

        // create destination folder, then move into it
        $this->postJson("/panel-api/documents/{$ownerId}/files/%2F", ['name' => 'Archive', 'type' => 'folder'])
            ->assertOk();

        $this->putJson("/panel-api/documents/{$ownerId}/files", [
            'operation' => 'move', 'ids' => ['/invoice-final.pdf'], 'target' => '/Archive',
        ])->assertOk()->assertJson(['result' => [['id' => '/Archive/invoice-final.pdf']]]);

        // copy it back to root
        $copy = $this->putJson("/panel-api/documents/{$ownerId}/files", [
            'operation' => 'copy', 'ids' => ['/Archive/invoice-final.pdf'], 'target' => '/',
        ])->assertOk()->json();
        $this->assertSame('/invoice-final.pdf', $copy['result'][0]['id']);

        // download works
        $this->get("/panel-api/documents/{$ownerId}/download?id=%2Finvoice-final.pdf")->assertOk();

        // delete both
        $this->deleteJson("/panel-api/documents/{$ownerId}/files", [
            'ids' => ['/invoice-final.pdf', '/Archive'],
        ])->assertOk();

        $this->getJson("/panel-api/documents/{$ownerId}/files")->assertOk()->assertJson([]);
    }

    public function test_non_admin_cannot_access_another_users_documents(): void
    {
        $this->actingAs($this->user);

        $this->getJson("/panel-api/documents/{$this->otherUser->id}/files")->assertStatus(403);
        $this->postJson("/panel-api/documents/{$this->otherUser->id}/files/%2F", [
            'name' => 'x', 'type' => 'folder',
        ])->assertStatus(403);
    }

    public function test_admin_can_access_any_users_documents(): void
    {
        $this->actingAs($this->otherUser);
        $this->postJson("/panel-api/documents/{$this->otherUser->id}/files/%2F", [
            'name' => 'Private', 'type' => 'folder',
        ])->assertOk();

        $this->actingAs($this->admin);
        $this->getJson("/panel-api/documents/{$this->otherUser->id}/files")
            ->assertOk()
            ->assertJsonFragment(['id' => '/Private']);
    }

    public function test_non_admin_cannot_list_users(): void
    {
        $this->actingAs($this->user);
        $this->getJson('/panel-api/documents/users')->assertStatus(403);

        $this->actingAs($this->admin);
        $this->getJson('/panel-api/documents/users')->assertOk();
    }

    public function test_unauthenticated_request_gets_json_401(): void
    {
        $this->getJson("/panel-api/documents/{$this->user->id}/files")
            ->assertStatus(401)
            ->assertJson(['error' => 'Unauthenticated.']);
    }

    public function test_duplicate_names_are_disambiguated(): void
    {
        $this->actingAs($this->user);
        $ownerId = $this->user->id;

        $this->postJson("/panel-api/documents/{$ownerId}/files/%2F", ['name' => 'Notes', 'type' => 'folder'])
            ->assertOk()->assertJson(['result' => ['id' => '/Notes']]);

        $this->postJson("/panel-api/documents/{$ownerId}/files/%2F", ['name' => 'Notes', 'type' => 'folder'])
            ->assertOk()->assertJson(['result' => ['id' => '/Notes (1)']]);
    }
}
