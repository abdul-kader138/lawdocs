<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_and_save_system_settings(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $this->actingAs($user)
            ->get('/admin/system-settings')
            ->assertOk();

        Setting::set('claude_model', 'claude-sonnet-4-6', 'claude');
        $this->assertSame('claude-sonnet-4-6', Setting::get('claude_model'));
    }
}
