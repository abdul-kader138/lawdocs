<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Providers\Filament\AdminPanelProvider;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SystemSettings extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 99;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return 'System Settings';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $superAdminName = (string) config('filament-shield.super_admin.name', 'super_admin');

        if ((bool) config('filament-shield.super_admin.enabled', true) && $user->hasRole($superAdminName)) {
            return true;
        }

        return $user->can('page_SystemSettings');
    }

    public function getTitle(): string
    {
        return 'System Settings';
    }

    public function getView(): string
    {
        return 'filament.pages.system-settings';
    }

    public function mount(): void
    {
        $this->form->fill([
            // General
            'app_name' => Setting::get('app_name', 'LawDocs'),
            'app_tagline' => Setting::get('app_tagline', 'Precedent-driven document assembly for your practice.'),

            // Appearance
            'admin_theme' => Setting::get('admin_theme', 'indigo'),
            'admin_panel_theme_mode' => Setting::get('admin_panel_theme_mode', 'dark'),
            'app_logo' => Setting::get('app_logo'),
            'app_icon' => Setting::get('app_icon'),
            'favicon' => Setting::get('favicon'),

            // Document Defaults
            'docx_font_family' => Setting::get('docx_font_family', 'Times New Roman'),
            'docx_font_size' => Setting::get('docx_font_size', 12),
            'async_generation_enabled' => Setting::get('async_generation_enabled', false),

            // Security
            'two_factor_enabled' => Setting::get('two_factor_enabled', true),

            // Email
            'mail_from_name' => Setting::get('mail_from_name', config('mail.from.name', '')),
            'mail_from_address' => Setting::get('mail_from_address', config('mail.from.address', '')),
            'mail_host' => Setting::get('mail_host', ''),
            'mail_port' => Setting::get('mail_port', 587),
            'mail_username' => Setting::get('mail_username', ''),
            'mail_password' => Setting::get('mail_password', ''),
            'mail_encryption' => Setting::get('mail_encryption', 'tls'),
            'staff_notification_email' => Setting::get('staff_notification_email', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Tabs::make('settings_tabs')->tabs([

                    // ── General ──────────────────────────────────────────────
                    Tab::make('General')
                        ->icon('heroicon-o-home')
                        ->schema([
                            Section::make('Application')
                                ->description('Shown in the admin panel header and on the login page.')
                                ->schema([
                                    TextInput::make('app_name')
                                        ->label('Application Name')
                                        ->required()
                                        ->maxLength(100),

                                    TextInput::make('app_tagline')
                                        ->label('Tagline')
                                        ->maxLength(200),
                                ])->columns(2),
                        ]),

                    // ── Appearance ───────────────────────────────────────────
                    Tab::make('Appearance')
                        ->icon('heroicon-o-swatch')
                        ->schema([
                            Section::make('Color Theme')
                                ->description('Choose a color scheme for the admin panel. Save and refresh to apply.')
                                ->schema([
                                    Radio::make('admin_theme')
                                        ->label('Admin Panel Theme')
                                        ->helperText('The selected theme applies to all admin panel pages.')
                                        ->options(
                                            collect(AdminPanelProvider::$themes)
                                                ->mapWithKeys(fn ($t, $key) => [$key => $t['label']])
                                                ->toArray()
                                        )
                                        ->columns(4)
                                        ->required(),
                                ]),

                            Section::make('Panel Mode')
                                ->description('Control the light/dark mode of the admin panel shell.')
                                ->schema([
                                    Radio::make('admin_panel_theme_mode')
                                        ->label('Admin Panel Mode')
                                        ->helperText('Changes take effect after saving and refreshing.')
                                        ->options([
                                            'light' => 'Light',
                                            'dark' => 'Dark',
                                            'system' => 'System',
                                            'high_contrast' => 'High Contrast',
                                            'sepia' => 'Sepia',
                                            'midnight' => 'Midnight',
                                        ])
                                        ->descriptions([
                                            'light' => 'Always show the admin panel in light mode.',
                                            'dark' => 'Always show the admin panel in dark mode.',
                                            'system' => "Follow the user's OS dark/light preference.",
                                            'high_contrast' => 'Stronger contrast dark mode for better accessibility.',
                                            'sepia' => 'Warm light theme with a soft paper-like tone.',
                                            'midnight' => 'Deeper blue-dark shell for a premium look.',
                                        ])
                                        ->inline()
                                        ->required(),
                                ]),

                            Section::make('Branding')
                                ->description('Upload logos and images. Run `php artisan storage:link` if images do not appear.')
                                ->schema([
                                    Grid::make(3)->schema([
                                        FileUpload::make('app_logo')
                                            ->label('Application Logo')
                                            ->image()
                                            ->disk('public')
                                            ->directory('branding')
                                            ->visibility('public')
                                            ->helperText('Shown in the admin panel sidebar header. Leave blank to use the application name as text.'),

                                        FileUpload::make('app_icon')
                                            ->label('App Icon / Favicon')
                                            ->image()
                                            ->disk('public')
                                            ->directory('branding')
                                            ->visibility('public')
                                            ->acceptedFileTypes(['image/x-icon', 'image/png', 'image/svg+xml'])
                                            ->helperText('Browser tab icon.'),

                                        FileUpload::make('favicon')
                                            ->label('Favicon (alternative)')
                                            ->image()
                                            ->disk('public')
                                            ->directory('branding')
                                            ->visibility('public')
                                            ->acceptedFileTypes(['image/x-icon', 'image/png', 'image/svg+xml'])
                                            ->helperText('Overrides the app icon for browser tabs.'),
                                    ]),
                                ]),
                        ]),

                    // ── Document Defaults ───────────────────────────────────
                    Tab::make('Document Defaults')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Section::make('Generated .docx Formatting')
                                ->description('Default font used when building a generated Word document.')
                                ->schema([
                                    TextInput::make('docx_font_family')
                                        ->label('Font Family')
                                        ->required()
                                        ->maxLength(100),

                                    TextInput::make('docx_font_size')
                                        ->label('Font Size (pt)')
                                        ->numeric()
                                        ->minValue(8)
                                        ->maxValue(24)
                                        ->required(),
                                ])->columns(2),

                            Section::make('Background Generation')
                                ->description('Off by default — generation runs synchronously and the requester waits ~10-30 seconds for the result.')
                                ->schema([
                                    Toggle::make('async_generation_enabled')
                                        ->label('Generate documents in the background')
                                        ->default(false)
                                        ->helperText('Only turn this on if a queue worker is actually running (`php artisan queue:work`, kept alive by Supervisor/systemd/similar) — otherwise every document request will sit at "pending" forever. The requester is told via the notification bell when their document is ready either way.'),
                                ]),
                        ]),

                    // ── Security ─────────────────────────────────────────────
                    Tab::make('Security')
                        ->icon('heroicon-o-shield-check')
                        ->schema([
                            Section::make('Two-Factor Authentication')
                                ->description('Applies firm-wide. Individual users still opt in from their own profile page — this is the master switch.')
                                ->schema([
                                    Toggle::make('two_factor_enabled')
                                        ->label('Allow two-factor authentication')
                                        ->default(true)
                                        ->helperText('Turning this off hides 2FA setup from every profile page and skips the login challenge for everyone, even users who previously enabled it.'),
                                ]),
                        ]),

                    // ── Email ────────────────────────────────────────────────
                    Tab::make('Email')
                        ->icon('heroicon-o-envelope')
                        ->schema([
                            Section::make('Sender')->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('mail_from_name')
                                        ->label('From Name')
                                        ->required()
                                        ->maxLength(100),

                                    TextInput::make('mail_from_address')
                                        ->label('From Address')
                                        ->email()
                                        ->required()
                                        ->maxLength(255),
                                ]),

                                TextInput::make('staff_notification_email')
                                    ->label('Staff Notification Email')
                                    ->email()
                                    ->maxLength(255)
                                    ->helperText('Where alerts go when a document generation fails. Leave blank to disable.'),
                            ]),

                            Section::make('SMTP Server')
                                ->description('Configure an SMTP provider to actually deliver emails. Leave the host blank to keep using the default log/array driver.')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('mail_host')
                                            ->label('SMTP Host')
                                            ->placeholder('smtp-relay.brevo.com')
                                            ->maxLength(255),

                                        TextInput::make('mail_port')
                                            ->label('SMTP Port')
                                            ->numeric()
                                            ->placeholder('587'),
                                    ]),

                                    Grid::make(2)->schema([
                                        TextInput::make('mail_username')
                                            ->label('SMTP Username')
                                            ->maxLength(255),

                                        TextInput::make('mail_password')
                                            ->label('SMTP Password')
                                            ->password()
                                            ->revealable()
                                            ->autocomplete('new-password')
                                            ->maxLength(255),
                                    ]),

                                    Select::make('mail_encryption')
                                        ->label('Encryption')
                                        ->options([
                                            'tls' => 'TLS',
                                            'ssl' => 'SSL',
                                        ])
                                        ->native(false),
                                ]),
                        ]),

                ])->persistTabInQueryString('tab'),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $groups = [
            'app_name' => 'general',
            'app_tagline' => 'general',
            'admin_theme' => 'appearance',
            'admin_panel_theme_mode' => 'appearance',
            'app_logo' => 'appearance',
            'app_icon' => 'appearance',
            'favicon' => 'appearance',
            'docx_font_family' => 'document',
            'docx_font_size' => 'document',
            'async_generation_enabled' => 'document',
            'two_factor_enabled' => 'security',
            'mail_from_name' => 'email',
            'mail_from_address' => 'email',
            'mail_host' => 'email',
            'mail_port' => 'email',
            'mail_username' => 'email',
            'mail_password' => 'email',
            'mail_encryption' => 'email',
            'staff_notification_email' => 'email',
        ];

        foreach ($data as $key => $value) {
            Setting::set($key, $value ?? '', $groups[$key] ?? 'general');
        }

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->submit('save'),
        ];
    }
}
