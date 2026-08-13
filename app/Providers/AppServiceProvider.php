<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationActivity;
use App\Listeners\LogPermissionActivity;
use App\Models\Setting;
use App\Policies\ActivityPolicy;
use App\Services\ClauseLibrary;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton: its cache is precedent-id-keyed with no other mutable
        // state, and every generator now resolves it directly (for
        // front_matter) as well as indirectly via ClauseSequenceRenderer —
        // without this, that's two independent instances parsing the same
        // .docx twice per document generation.
        $this->app->singleton(ClauseLibrary::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->applyMailSettings();

        Event::subscribe(LogAuthenticationActivity::class);
        Event::subscribe(LogPermissionActivity::class);

        // Activity (Spatie\Activitylog\Models\Activity) lives outside
        // App\Models, so Laravel's policy auto-discovery never finds the
        // shield:generate'd App\Policies\ActivityPolicy on its own —
        // ActivityLogResource's viewAny/view authorization depends on this.
        Gate::policy(Activity::class, ActivityPolicy::class);
    }

    /**
     * Mail config is read by Laravel's mailer once per process and cached
     * internally, so it must be pushed into config() at boot rather than
     * read on demand like other settings.
     */
    private function applyMailSettings(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        config([
            'mail.from.name'    => Setting::get('mail_from_name', config('mail.from.name')),
            'mail.from.address' => Setting::get('mail_from_address', config('mail.from.address')),
        ]);

        if ($host = Setting::get('mail_host')) {
            config([
                'mail.default'               => 'smtp',
                'mail.mailers.smtp.host'     => $host,
                'mail.mailers.smtp.port'     => Setting::get('mail_port', 587),
                'mail.mailers.smtp.username' => Setting::get('mail_username'),
                'mail.mailers.smtp.password' => Setting::get('mail_password'),
                'mail.mailers.smtp.scheme'   => Setting::get('mail_encryption') ?: null,
            ]);
        }
    }
}
