<?php

namespace App\Providers\Filament;

use App\Models\Setting;
use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Pages\Auth\Login;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(fn () => Setting::get('app_name', 'LawDocs'))
            ->brandLogo(fn () => self::resolveBrandLogoUrl())
            ->favicon(fn () => self::resolveFaviconUrl())
            ->colors([
                'primary' => self::resolveThemeColor(),
            ])
            ->defaultThemeMode(self::resolveDefaultThemeMode())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_START,
                fn () => view('components.auth-brand-panel'),
                scopes: [Login::class],
            )
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    // Wrapped in try/catch throughout: this provider boots on every artisan
    // command, including the very first `migrate` on a fresh install, before
    // the settings table exists.

    protected static function resolveThemeColor(): array
    {
        $colorMap = [
            'indigo'  => Color::Indigo,
            'amber'   => Color::Amber,
            'emerald' => Color::Emerald,
            'rose'    => Color::Rose,
            'violet'  => Color::Violet,
            'sky'     => Color::Sky,
            'slate'   => Color::Slate,
        ];

        try {
            $theme = Setting::get('admin_theme', 'indigo');
        } catch (\Throwable) {
            $theme = 'indigo';
        }

        return $colorMap[$theme] ?? Color::Indigo;
    }

    protected static function resolveDefaultThemeMode(): ThemeMode
    {
        try {
            $mode = Setting::get('admin_panel_theme_mode', 'dark');
        } catch (\Throwable) {
            $mode = 'dark';
        }

        return match ($mode) {
            'light'  => ThemeMode::Light,
            'system' => ThemeMode::System,
            default  => ThemeMode::Dark,
        };
    }

    protected static function resolveBrandLogoUrl(): ?string
    {
        try {
            $path = Setting::get('app_logo');
        } catch (\Throwable) {
            return null;
        }

        return $path ? Storage::disk('public')->url($path) : null;
    }

    protected static function resolveFaviconUrl(): ?string
    {
        try {
            $path = Setting::get('favicon');
        } catch (\Throwable) {
            return null;
        }

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
