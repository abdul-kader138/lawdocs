<?php

namespace App\Filament\Auth;

use App\Models\Setting;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Illuminate\Support\HtmlString;

/**
 * Adds an avatar upload and 2FA self-service management on top of Filament's
 * stock profile page — the enable/disable/recovery-code actions live here
 * (getHeaderActions()) rather than a dedicated route, matching the pattern
 * of everything else on this page being "one self-contained profile screen."
 * See App\Filament\Auth\Login for the other half (the login-time challenge).
 */
class EditProfile extends BaseEditProfile
{
    /**
     * @return array<int|string, string|Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        Grid::make(3)->schema([
                            Section::make('Profile Picture')
                                ->schema([
                                    FileUpload::make('avatar')
                                        ->label('')
                                        ->avatar()
                                        ->imageEditor()
                                        ->circleCropper()
                                        ->disk('public')
                                        ->directory('avatars')
                                        ->visibility('public'),
                                ])
                                ->columnSpan(1),

                            Section::make('Personal Information')
                                ->schema([
                                    $this->getNameFormComponent(),
                                    $this->getEmailFormComponent(),
                                ])
                                ->columnSpan(2),
                        ]),

                        Section::make('Change Password')
                            ->schema([
                                Grid::make(2)->schema([
                                    $this->getPasswordFormComponent(),
                                    $this->getPasswordConfirmationFormComponent(),
                                ]),
                            ]),

                        Section::make('Two-Factor Authentication')
                            ->schema([
                                Placeholder::make('two_factor_status')
                                    ->label('')
                                    ->content(fn () => $this->twoFactorStatusText()),
                            ]),
                    ])
                    ->operation('edit')
                    ->model($this->getUser())
                    ->statePath('data'),
            ),
        ];
    }

    private function twoFactorStatusText(): string
    {
        if (! $this->twoFactorGloballyEnabled()) {
            return 'Two-factor authentication is currently disabled application-wide by an administrator.';
        }

        return $this->getUser()->hasEnabledTwoFactorAuthentication()
            ? 'Two-factor authentication is enabled on your account.'
            : 'Two-factor authentication is not enabled on your account.';
    }

    private function twoFactorGloballyEnabled(): bool
    {
        return (bool) Setting::get('two_factor_enabled', true);
    }

    /**
     * All four actions are always returned (never conditionally omitted)
     * and gated purely via ->visible() closures — deliberately, so a test
     * (or anything else introspecting actions) can assert visibility
     * rather than existence. Only the "globally disabled" case is a real
     * absence, since there is nothing to manage at all in that state.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        if (! $this->twoFactorGloballyEnabled()) {
            return [];
        }

        /** @var User $user */
        $user = $this->getUser();
        $isEnabled = fn () => $user->fresh()->hasEnabledTwoFactorAuthentication();

        return [
            $this->enableTwoFactorAction($user)->visible(fn () => ! $isEnabled()),

            Action::make('showRecoveryCodes')
                ->label('Show Recovery Codes')
                ->icon('heroicon-o-key')
                ->color('gray')
                ->visible($isEnabled)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn () => view('filament.auth.recovery-codes-modal', [
                    'codes' => $user->two_factor_recovery_codes ?? [],
                ])),

            Action::make('regenerateRecoveryCodes')
                ->label('Regenerate Recovery Codes')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible($isEnabled)
                ->requiresConfirmation()
                ->modalDescription('Your existing recovery codes will stop working immediately.')
                ->action(function () use ($user) {
                    $user->forceFill([
                        'two_factor_recovery_codes' => app(TwoFactorAuthenticationService::class)->generateRecoveryCodes(),
                    ])->save();

                    Notification::make()->success()->title('Recovery codes regenerated')->send();
                }),

            Action::make('disableTwoFactor')
                ->label('Disable Two-Factor Authentication')
                ->icon('heroicon-o-shield-exclamation')
                ->color('danger')
                ->visible($isEnabled)
                ->requiresConfirmation()
                ->modalHeading('Disable two-factor authentication?')
                ->modalDescription('You will no longer be asked for a code when signing in.')
                ->action(function () use ($user) {
                    $user->forceFill([
                        'two_factor_secret' => null,
                        'two_factor_recovery_codes' => null,
                        'two_factor_confirmed_at' => null,
                    ])->save();

                    Notification::make()->success()->title('Two-factor authentication disabled')->send();
                }),
        ];
    }

    private function enableTwoFactorAction(User $user): Action
    {
        $service = app(TwoFactorAuthenticationService::class);

        return Action::make('enableTwoFactor')
            ->label('Enable Two-Factor Authentication')
            ->icon('heroicon-o-shield-check')
            ->fillForm(fn () => ['secret' => $service->generateSecretKey()])
            ->form([
                Hidden::make('secret'),
                Placeholder::make('qr_code')
                    ->label('Scan this with your authenticator app')
                    ->content(fn (Get $get) => new HtmlString(
                        '<div style="display:flex;flex-direction:column;align-items:center;gap:.5rem;">'
                        .'<img src="'.$service->qrCodeSvg($user, (string) $get('secret')).'" alt="Two-factor setup QR code" />'
                        .'<code style="font-size:.75rem;">'.e((string) $get('secret')).'</code>'
                        .'</div>'
                    )),
                TextInput::make('code')
                    ->label('Authentication Code')
                    ->helperText('Enter the 6-digit code your app just generated to confirm setup.')
                    ->required()
                    ->autocomplete('one-time-code')
                    ->extraInputAttributes(['inputmode' => 'numeric'])
                    ->rule(fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($service, $get) {
                        if (! $service->verify((string) $get('secret'), (string) $value)) {
                            $fail('The provided code was invalid.');
                        }
                    }),
            ])
            ->action(function (array $data) use ($user, $service) {
                $user->forceFill([
                    'two_factor_secret' => $data['secret'],
                    'two_factor_recovery_codes' => $service->generateRecoveryCodes(),
                    'two_factor_confirmed_at' => now(),
                ])->save();

                Notification::make()->success()->title('Two-factor authentication enabled')->send();
            });
    }
}
