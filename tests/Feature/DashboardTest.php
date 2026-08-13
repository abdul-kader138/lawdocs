<?php

namespace Tests\Feature;

use App\Filament\Widgets\DocumentRequestsChartWidget;
use App\Filament\Widgets\RecentDocumentRequestsWidget;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\WelcomeHeaderWidget;
use App\Models\DocumentRequest;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\WillPrecedentFixture;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;
    use WillPrecedentFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShieldSeeder::class);
    }

    public function test_super_admin_and_panel_user_can_both_load_the_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin)->get('/admin')->assertOk();

        $staff = User::factory()->create();
        $staff->assignRole('panel_user');
        $this->actingAs($staff)->get('/admin')->assertOk();
    }

    public function test_stats_overview_counts_are_correct(): void
    {
        $precedent = $this->makeWillPrecedent();
        $user = User::factory()->create();

        DocumentRequest::create(['precedent_id' => $precedent->id, 'precedent_title_snapshot' => $precedent->title, 'requested_by' => $user->id, 'answers' => [], 'status' => 'pending']);
        DocumentRequest::create(['precedent_id' => $precedent->id, 'precedent_title_snapshot' => $precedent->title, 'requested_by' => $user->id, 'answers' => [], 'status' => 'processing']);
        DocumentRequest::create(['precedent_id' => $precedent->id, 'precedent_title_snapshot' => $precedent->title, 'requested_by' => $user->id, 'answers' => [], 'status' => 'failed']);
        // Completed and awaiting review (requires_review defaults true, not approved).
        DocumentRequest::create(['precedent_id' => $precedent->id, 'precedent_title_snapshot' => $precedent->title, 'requested_by' => $user->id, 'answers' => [], 'status' => 'completed']);
        // Completed and already approved — should NOT count as awaiting review.
        DocumentRequest::create(['precedent_id' => $precedent->id, 'precedent_title_snapshot' => $precedent->title, 'requested_by' => $user->id, 'answers' => [], 'status' => 'completed', 'approved_at' => now(), 'approved_by' => $user->id]);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        Livewire::test(StatsOverview::class)
            ->assertSee('In Progress')
            ->assertSee('2') // pending + processing
            ->assertSee('Awaiting Review')
            ->assertSee('1');
    }

    public function test_recent_document_requests_widget_lists_latest_requests(): void
    {
        $precedent = $this->makeWillPrecedent();
        $user = User::factory()->create(['name' => 'Ashley Requester']);
        DocumentRequest::create(['precedent_id' => $precedent->id, 'precedent_title_snapshot' => $precedent->title, 'requested_by' => $user->id, 'answers' => [], 'status' => 'completed']);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        Livewire::test(RecentDocumentRequestsWidget::class)
            ->assertSee($precedent->title)
            ->assertSee('Ashley Requester');
    }

    public function test_chart_widget_renders_for_all_filter_ranges(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        foreach (['7', '14', '30'] as $filter) {
            Livewire::test(DocumentRequestsChartWidget::class)
                ->set('filter', $filter)
                ->assertOk();
        }
    }

    public function test_welcome_header_widget_shows_greeting_and_stats(): void
    {
        $admin = User::factory()->create(['name' => 'Ashley Admin']);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        Livewire::test(WelcomeHeaderWidget::class)
            ->assertSee('Ashley Admin')
            ->assertOk();
    }
}
