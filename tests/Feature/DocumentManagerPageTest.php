<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentManagerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_the_documents_page_with_switcher(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $response = $this->actingAs($admin)->get('/admin/document-manager');

        $response->assertOk();
        $response->assertSee('document-manager-root', false);

        // Blade's {{ }} HTML-entity-escapes the embedded JSON (correct: the browser
        // decodes entities back before JS reads dataset.props via JSON.parse).
        $response->assertSee(htmlspecialchars('"isAdmin":true'), false);
        $response->assertSee(htmlspecialchars('"currentUserId":'.$admin->id), false);
    }

    public function test_non_admin_can_render_the_documents_page(): void
    {
        Role::firstOrCreate(['name' => 'panel_user', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('panel_user');

        $response = $this->actingAs($user)->get('/admin/document-manager');

        $response->assertOk();
        $response->assertSee(htmlspecialchars('"isAdmin":false'), false);
    }
}
