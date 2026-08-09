<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        // Admin panel roles.
        // super_admin: full access to everything (Shield's gate-before bypass).
        // operator: staff who can also manage precedents (granted *_precedent
        //           permissions manually via the Shield Roles UI after setup).
        // panel_user: staff who can only request/view documents, never precedents.
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'operator',    'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'panel_user',  'guard_name' => 'web']);

        // Default super admin user (credentials via .env: ADMIN_EMAIL, ADMIN_PASSWORD)
        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@admin.com')],
            [
                'name'              => env('ADMIN_NAME', 'Super Admin'),
                'password'          => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole($superAdmin);

        $this->command->info('Admin user ready.');
        $this->command->table(
            ['Field', 'Value'],
            [
                ['Email',    $admin->email],
                ['Password', env('ADMIN_PASSWORD', 'password')],
                ['Role',     'super_admin'],
                ['URL',      url('/admin')],
            ]
        );
    }
}
