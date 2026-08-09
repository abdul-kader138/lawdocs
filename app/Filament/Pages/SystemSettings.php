<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Section;
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
            'docx_font_family' => 'document',
            'docx_font_size'   => 'document',
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
