<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        // Permission rows aren't created by a migration — they only exist once
        // `shield:generate` has run against whatever database is active. Doing
        // that here (rather than assuming it was already run by hand) is what
        // makes this seeder work on a fresh install AND in the test suite's
        // own database, not just on whichever machine happened to run the CLI
        // command first.
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--no-interaction' => true,
            // Without this, re-running the seeder (e.g. on every deploy)
            // overwrites hand-extended policies, like DocumentRequestPolicy's
            // custom download() method, with the bare generated version.
            '--ignore-existing-policies' => true,
        ]);

        // Admin panel roles.
        // super_admin: full access to everything.
        // operator: staff who can also manage precedents.
        // panel_user: staff who can only request/view documents, never precedents.
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $operator = Role::firstOrCreate(['name' => 'operator',    'guard_name' => 'web']);
        $panelUser = Role::firstOrCreate(['name' => 'panel_user',  'guard_name' => 'web']);

        // filament-shield's config ships with super_admin.define_via_gate = false,
        // meaning there is NO automatic Gate::before bypass for this role — it
        // only gets full access because every permission is explicitly synced to
        // it here (exactly what `php artisan shield:super-admin` would otherwise
        // do interactively). Must run after shield:generate has created the
        // permission rows, or this silently syncs zero permissions.
        $superAdmin->syncPermissions(Permission::all());

        // Note the `::` in these permission names — that's Shield's generated
        // slug for the multi-word "DocumentRequest" model (view_document::request,
        // not view_document_request), not a typo.
        $panelUserPermissions = [
            'view_any_document::request',
            'view_document::request',
            'create_document::request',
            // Clients/contacts are shared reference data, not a workflow
            // record with an approval gate — any staff member who can raise
            // a document request should also be able to maintain the
            // reusable client/contact details that prefill one. Deliberately
            // no delete_client here though: removing a client record (and
            // its contacts, cascade) is a more consequential action than
            // creating/editing one.
            'view_any_client',
            'view_client',
            'create_client',
            'update_client',
        ];

        $panelUser->syncPermissions(
            Permission::whereIn('name', $panelUserPermissions)->get()
        );

        // operator gets everything panel_user gets, plus full precedent
        // management and full client management (including delete).
        $operator->syncPermissions(
            Permission::where('name', 'like', '%_document::request')
                ->orWhere('name', 'like', '%_precedent')
                ->orWhere('name', 'like', '%_client')
                ->get()
        );

        // Default super admin user (credentials via .env: ADMIN_EMAIL, ADMIN_PASSWORD)
        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@admin.com')],
            [
                'name' => env('ADMIN_NAME', 'Super Admin'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
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
