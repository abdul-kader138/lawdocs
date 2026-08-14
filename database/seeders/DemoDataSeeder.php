<?php

namespace Database\Seeders;

use App\Jobs\GenerateDocumentJob;
use App\Models\Client;
use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Walkthrough data for the Operations Manual. This is part of the default
 * `db:seed` pipeline so a fresh deployment has the same useful starter data
 * as the local environment. Idempotent: safe to re-run.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PrecedentSeeder::class);

        $will = Precedent::where('title', 'Last Will and Testament')->firstOrFail();
        $poa = Precedent::where('title', 'Power of Attorney')->firstOrFail();
        $eg = Precedent::where('title', 'Enduring Guardianship')->firstOrFail();

        // Client Mapping — so the "pick a client" prefill described in the
        // manual actually has something to demonstrate.
        $will->update(['client_field_map' => [
            'name' => 'testator_name', 'dob' => 'testator_dob', 'gender' => 'testator_gender',
            'street' => 'testator_street', 'suburb' => 'testator_suburb',
        ]]);
        $poa->update(['client_field_map' => [
            'name' => 'principal_name', 'dob' => 'principal_dob',
        ]]);

        [$operator, $paralegal, $panelUser] = $this->seedUsers();
        [$sullivan, $dalton, $osei] = $this->seedClients();

        $this->seedFullLifecycleWill($will, $sullivan, $operator);
        $this->seedPendingReviewWill($will, $dalton, $paralegal);
        $this->seedApprovedPoa($poa, $osei, $operator);
        $this->seedFailedGeneration($eg, $operator);

        $this->command->info('Demo data ready.');
        $this->command->table(['Email', 'Password', 'Role'], [
            [$operator->email, 'password', 'operator'],
            [$paralegal->email, 'password', 'operator (restricted to: will)'],
            [$panelUser->email, 'password', 'panel_user'],
        ]);
    }

    /** @return array{0: User, 1: User, 2: User} */
    private function seedUsers(): array
    {
        $operator = User::firstOrCreate(
            ['email' => 'operator@lawdocs.test'],
            ['name' => 'Priya Anand', 'password' => Hash::make('password'), 'email_verified_at' => now()]
        );
        $operator->syncRoles(['operator']);

        $paralegal = User::firstOrCreate(
            ['email' => 'paralegal@lawdocs.test'],
            ['name' => 'Tom Whitfield', 'password' => Hash::make('password'), 'email_verified_at' => now()]
        );
        $paralegal->syncRoles(['operator']);
        $paralegal->update(['precedent_categories' => ['will']]);

        $panelUser = User::firstOrCreate(
            ['email' => 'staff@lawdocs.test'],
            ['name' => 'Grace Odongo', 'password' => Hash::make('password'), 'email_verified_at' => now()]
        );
        $panelUser->syncRoles(['panel_user']);

        return [$operator, $paralegal, $panelUser];
    }

    /** @return array{0: Client, 1: Client, 2: Client} */
    private function seedClients(): array
    {
        $sullivan = Client::firstOrCreate(
            ['email' => 'margaret.sullivan@example.com'],
            [
                'name' => 'Margaret Ellen Sullivan', 'gender' => 'female', 'dob' => '1958-03-14',
                'phone' => '02 9411 2233', 'street' => '12 Fig Tree Lane', 'suburb' => 'Chatswood',
                'state' => 'NSW', 'postcode' => '2067',
            ]
        );
        $sullivan->contacts()->firstOrCreate(
            ['name' => 'David Sullivan'],
            ['relationship' => 'Son', 'phone' => '0412 555 101', 'email' => 'david.sullivan@example.com']
        );

        $dalton = Client::firstOrCreate(
            ['email' => 'henry.dalton@example.com'],
            [
                'name' => 'Henry Thomas Dalton', 'gender' => 'male', 'dob' => '1971-11-02',
                'phone' => '02 9633 4477', 'street' => '88 Wattle Street', 'suburb' => 'Parramatta',
                'state' => 'NSW', 'postcode' => '2150',
            ]
        );

        $osei = Client::firstOrCreate(
            ['email' => 'amara.osei@example.com'],
            [
                'name' => 'Amara Osei', 'gender' => 'female', 'dob' => '1965-06-21',
                'phone' => '02 9977 8811', 'street' => '5 Bridge Road', 'suburb' => 'Manly',
                'state' => 'NSW', 'postcode' => '2095',
            ]
        );

        return [$sullivan, $dalton, $osei];
    }

    /**
     * The complete journey the manual walks through end to end: generate,
     * approve, send for signature, mark as signed, record witnesses.
     */
    private function seedFullLifecycleWill(Precedent $will, Client $sullivan, User $operator): void
    {
        if (DocumentRequest::where('case_reference', 'SUL-2026-014')->exists()) {
            return;
        }

        $documentRequest = DocumentRequest::create([
            'precedent_id' => $will->id,
            'precedent_title_snapshot' => $will->title,
            'precedent_jurisdiction_snapshot' => $will->jurisdiction,
            'client_id' => $sullivan->id,
            'requested_by' => $operator->id,
            'case_reference' => 'SUL-2026-014',
            'answers' => [
                'testator_name' => 'Margaret Ellen Sullivan',
                'testator_dob' => '1958-03-14',
                'testator_street' => '12 Fig Tree Lane',
                'testator_suburb' => 'Chatswood',
                'testator_state' => 'State of New South Wales',
                'testator_gender' => 'female',
                'executor_name' => 'David Sullivan',
                'executor_gender' => 'male',
                'alternate_executor_name' => 'Rebecca Sullivan',
                'alternate_executor_gender' => 'female',
            ],
            'status' => 'pending',
        ]);

        $documentRequest->parties()->create([
            'group_key' => 'beneficiaries', 'position' => 0,
            'data' => ['name' => 'David Sullivan', 'share' => 60, 'gender' => 'male', 'per_stirpes' => true],
        ]);
        $documentRequest->parties()->create([
            'group_key' => 'beneficiaries', 'position' => 1,
            'data' => ['name' => 'Emily Sullivan', 'share' => 40, 'gender' => 'female', 'per_stirpes' => false],
        ]);

        GenerateDocumentJob::dispatchSync($documentRequest);

        $documentRequest->update([
            'approved_at' => now()->subDays(2),
            'approved_by' => $operator->id,
            'sent_for_signature_at' => now()->subDay(),
            'signed_at' => now()->subHours(3),
        ]);

        $documentRequest->witnesses()->createMany([
            ['position' => 0, 'name' => 'Karen Blake', 'address' => '4 Orchard Rd, Chatswood NSW 2067', 'occupation' => 'Accountant'],
            ['position' => 1, 'name' => 'Michael Ford', 'address' => '19 Elm St, Chatswood NSW 2067', 'occupation' => 'Solicitor'],
        ]);
    }

    /** Completed, but never approved — demonstrates the "Needs review" badge. */
    private function seedPendingReviewWill(Precedent $will, Client $dalton, User $paralegal): void
    {
        if (DocumentRequest::where('case_reference', 'DAL-2026-009')->exists()) {
            return;
        }

        $documentRequest = DocumentRequest::create([
            'precedent_id' => $will->id,
            'precedent_title_snapshot' => $will->title,
            'precedent_jurisdiction_snapshot' => $will->jurisdiction,
            'client_id' => $dalton->id,
            'requested_by' => $paralegal->id,
            'case_reference' => 'DAL-2026-009',
            'answers' => [
                'testator_name' => 'Henry Thomas Dalton',
                'testator_dob' => '1971-11-02',
                'testator_street' => '88 Wattle Street',
                'testator_suburb' => 'Parramatta',
                'testator_state' => 'State of New South Wales',
                'testator_gender' => 'male',
                'executor_name' => 'Peter Dalton',
                'executor_gender' => 'male',
                'alternate_executor_name' => null,
                'alternate_executor_gender' => null,
            ],
            'status' => 'pending',
        ]);

        $documentRequest->parties()->create([
            'group_key' => 'beneficiaries', 'position' => 0,
            'data' => ['name' => 'Olivia Dalton', 'share' => 100, 'gender' => 'female', 'per_stirpes' => false],
        ]);

        GenerateDocumentJob::dispatchSync($documentRequest);
    }

    /** Completed and approved, but not yet sent for signature — a mid-workflow snapshot. */
    private function seedApprovedPoa(Precedent $poa, Client $osei, User $operator): void
    {
        if (DocumentRequest::where('case_reference', 'OSE-2026-002')->exists()) {
            return;
        }

        $documentRequest = DocumentRequest::create([
            'precedent_id' => $poa->id,
            'precedent_title_snapshot' => $poa->title,
            'precedent_jurisdiction_snapshot' => $poa->jurisdiction,
            'client_id' => $osei->id,
            'requested_by' => $operator->id,
            'case_reference' => 'OSE-2026-002',
            'answers' => [
                'principal_name' => 'Amara Osei',
                'principal_dob' => '1965-06-21',
                'principal_address' => '5 Bridge Road, Manly NSW 2095',
                'is_enduring' => true,
                'attorneys_act_jointly' => false,
            ],
            'status' => 'pending',
        ]);

        $documentRequest->parties()->create([
            'group_key' => 'attorneys', 'position' => 0,
            'data' => ['name' => 'Kwame Osei', 'address' => '5 Bridge Road, Manly NSW 2095', 'relationship' => 'Son'],
        ]);
        $documentRequest->parties()->create([
            'group_key' => 'attorneys', 'position' => 1,
            'data' => ['name' => 'Linda Park', 'address' => '22 Harbour View, Manly NSW 2095', 'relationship' => 'Friend'],
        ]);

        GenerateDocumentJob::dispatchSync($documentRequest);

        $documentRequest->update(['approved_at' => now()->subHours(6), 'approved_by' => $operator->id]);
    }

    /**
     * A hand-set failure record — demonstrates the "Failed" badge and the
     * error message a staff member would actually see. The wording matches
     * ClauseTemplateException::unresolvedPlaceholder() verbatim rather than
     * triggering it live, since forcing this precedent's real generator into
     * a genuine failure requires deliberately malformed input better kept
     * out of a demo dataset that's otherwise all valid, working examples.
     */
    private function seedFailedGeneration(Precedent $eg, User $operator): void
    {
        if (DocumentRequest::where('case_reference', 'EG-2026-DEMO')->exists()) {
            return;
        }

        DocumentRequest::create([
            'precedent_id' => $eg->id,
            'precedent_title_snapshot' => $eg->title,
            'precedent_jurisdiction_snapshot' => $eg->jurisdiction,
            'requested_by' => $operator->id,
            'case_reference' => 'EG-2026-DEMO',
            'answers' => ['principal_address' => '17 Test Street, Sydney NSW 2000'],
            'status' => 'failed',
            'error_message' => 'Unresolved placeholder {{answers.principal_name}} — "answers" is always in scope, but a repeat alias is only in scope inside its own [[REPEAT:...]] block.',
        ]);
    }
}
