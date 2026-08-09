<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
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
            // Claude AI
            'claude_api_key'     => Setting::get('claude_api_key', ''),
            'claude_model'       => Setting::get('claude_model', config('services.anthropic.model')),
            'claude_max_tokens'  => Setting::get('claude_max_tokens', 8192),
            'claude_temperature' => Setting::get('claude_temperature', 0.2),

            // Document Defaults
            'docx_font_family' => Setting::get('docx_font_family', 'Times New Roman'),
            'docx_font_size'   => Setting::get('docx_font_size', 12),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Tabs::make('settings_tabs')->tabs([

                    // ── Claude AI ────────────────────────────────────────────
                    Tab::make('Claude AI')
                        ->icon('heroicon-o-sparkles')
                        ->schema([
                            Section::make('Anthropic API')
                                ->description('Configure the Claude model used to draft documents.')
                                ->schema([
                                    TextInput::make('claude_api_key')
                                        ->label('API Key')
                                        ->password()
                                        ->revealable()
                                        ->maxLength(255)
                                        ->autocomplete('new-password')
                                        ->helperText('Your Anthropic API key from console.anthropic.com. Leave blank to use the ANTHROPIC_API_KEY env value.'),

                                    TextInput::make('claude_model')
                                        ->label('Model')
                                        ->required()
                                        ->maxLength(100)
                                        ->helperText('e.g. claude-sonnet-4-6. A stronger model is recommended over Haiku given drafting-quality stakes.'),

                                    TextInput::make('claude_max_tokens')
                                        ->label('Max Tokens')
                                        ->numeric()
                                        ->minValue(1024)
                                        ->maxValue(64000)
                                        ->required()
                                        ->helperText('Output limit for a drafted document. If a draft is cut off (truncated), raise this.'),

                                    TextInput::make('claude_temperature')
                                        ->label('Temperature')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(1)
                                        ->step(0.1)
                                        ->required()
                                        ->helperText('Low (0-0.3) is recommended for legal drafting — consistency over creativity.'),
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

                ])->persistTabInQueryString('tab'),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $groups = [
            'claude_api_key'     => 'claude',
            'claude_model'       => 'claude',
            'claude_max_tokens'  => 'claude',
            'claude_temperature' => 'claude',
            'docx_font_family'   => 'document',
            'docx_font_size'     => 'document',
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
            \Filament\Actions\Action::make('save')
                ->label('Save')
                ->submit('save'),
        ];
    }
}
