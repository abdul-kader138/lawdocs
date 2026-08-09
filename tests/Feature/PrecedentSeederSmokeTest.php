<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PrecedentSeeder;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrecedentSeederSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_precedent_list_and_edit_pages_render_with_real_seeded_data(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(PrecedentSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get('/admin/precedents')
            ->assertOk()
            ->assertSee('Last Will and Testament')
            ->assertSee('Power of Attorney');

        $will = \App\Models\Precedent::where('title', 'Last Will and Testament')->firstOrFail();

        $this->actingAs($admin)
            ->get("/admin/precedents/{$will->id}/edit")
            ->assertOk()
            ->assertSee('LAST WILL AND TESTAMENT'); // the extracted-text preview field
    }

    public function test_document_request_create_wizard_renders_with_real_precedent_options(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(PrecedentSeeder::class);

        $staff = User::factory()->create();
        $staff->assignRole('panel_user');

        $this->actingAs($staff)
            ->get('/admin/document-requests/create')
            ->assertOk();
    }
}
