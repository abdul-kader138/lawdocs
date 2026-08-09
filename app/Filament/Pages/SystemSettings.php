<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
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
    protected static ?int    $navigationSort = 99;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return 'System Settings';
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
            'app_name'    => Setting::get('app_name', 'LawDocs'),
            'app_tagline' => Setting::get('app_tagline', 'Precedent-driven document assembly for your practice.'),

            // Appearance
            'admin_theme'            => Setting::get('admin_theme', 'indigo'),
            'admin_panel_theme_mode' => Setting::get('admin_panel_theme_mode', 'dark'),
            'app_logo'               => Setting::get('app_logo'),
            'favicon'                => Setting::get('favicon'),

            // Document Defaults
            'docx_font_family' => Setting::get('docx_font_family', 'Times New Roman'),
            'docx_font_size'   => Setting::get('docx_font_size', 12),

            // Email
            'mail_from_name'           => Setting::get('mail_from_name', config('mail.from.name', '')),
            'mail_from_address'        => Setting::get('mail_from_address', config('mail.from.address', '')),
            'mail_host'                => Setting::get('mail_host', ''),
            'mail_port'                => Setting::get('mail_port', 587),
            'mail_username'            => Setting::get('mail_username', ''),
            'mail_password'            => Setting::get('mail_password', ''),
            'mail_encryption'          => Setting::get('mail_encryption', 'tls'),
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
                            Section::make('Admin Panel')
                                ->schema([
                                    Grid::make(2)->schema([
                                        Select::make('admin_theme')
                                            ->label('Primary Color')
                                            ->options([
                                                'indigo'  => 'Indigo',
                                                'amber'   => 'Amber',
                                                'emerald' => 'Emerald',
                                                'rose'    => 'Rose',
                                                'violet'  => 'Violet',
                                                'sky'     => 'Sky',
                                                'slate'   => 'Slate',
                                            ])
                                            ->native(false)
                                            ->required(),

                                        Select::make('admin_panel_theme_mode')
                                            ->label('Default Mode')
                                            ->options([
                                                'dark'   => 'Dark',
                                                'light'  => 'Light',
                                                'system' => 'Follow System',
                                            ])
                                            ->native(false)
                                            ->required(),
                                    ]),

                                    Grid::make(2)->schema([
                                        FileUpload::make('app_logo')
                                            ->label('Logo')
                                            ->image()
                                            ->disk('public')
                                            ->directory('branding')
                                            ->helperText('Shown in the admin panel sidebar header. Leave blank to use the application name as text.'),

                                        FileUpload::make('favicon')
                                            ->label('Favicon')
                                            ->image()
                                            ->disk('public')
                                            ->directory('branding')
                                            ->helperText('Browser tab icon.'),
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
            'app_name'                 => 'general',
            'app_tagline'              => 'general',
            'admin_theme'              => 'appearance',
            'admin_panel_theme_mode'   => 'appearance',
            'app_logo'                 => 'appearance',
            'favicon'                  => 'appearance',
            'docx_font_family'         => 'document',
            'docx_font_size'           => 'document',
            'mail_from_name'           => 'email',
            'mail_from_address'        => 'email',
            'mail_host'                => 'email',
            'mail_port'                => 'email',
            'mail_username'            => 'email',
            'mail_password'            => 'email',
            'mail_encryption'          => 'email',
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
