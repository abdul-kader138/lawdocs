<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Staff account management — creating/editing users and assigning Spatie
 * roles. Deliberately NOT synced to operator/panel_user in ShieldSeeder:
 * account provisioning (including who can be granted super_admin) stays
 * super_admin-only, a stricter default than precedent/document-request
 * management.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 90;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Account')
                ->schema([
                    TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(User::class, 'email', ignoreRecord: true),

                    TextInput::make('password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->minLength(8)
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                        ->helperText('Leave blank to keep the current password.'),

                    TextInput::make('password_confirmation')
                        ->label('Confirm Password')
                        ->password()
                        ->revealable()
                        ->dehydrated(false)
                        ->same('password')
                        ->required(fn (string $operation) => $operation === 'create'),
                ])->columns(2),

            Section::make('Roles')
                ->description('What this user can access in the admin panel.')
                ->schema([
                    Select::make('roles')
                        ->label('Roles')
                        ->multiple()
                        ->relationship('roles', 'name')
                        ->options(fn () => Role::pluck('name', 'id'))
                        ->preload()
                        ->helperText('super_admin: full access. operator: precedents + document requests. panel_user: document requests only.')
                        // Goes through syncRoles() rather than Filament's default
                        // pivot ->sync() so RoleAttached/RoleDetached fire and
                        // the change lands in the audit log (see
                        // App\Listeners\LogPermissionActivity).
                        ->saveRelationshipsUsing(fn ($record, $state) => $record->syncRoles($state)),
                ]),

            Section::make('Precedent Access')
                ->description('Only relevant for a user who can manage precedents (operator/super_admin) — leave blank for no restriction.')
                ->schema([
                    Select::make('precedent_categories')
                        ->label('Restrict to categories')
                        ->multiple()
                        ->options([
                            'will' => 'Will',
                            'power_of_attorney' => 'Power of Attorney',
                            'trust' => 'Trust',
                            'other' => 'Other',
                        ])
                        ->native(false)
                        ->helperText('If set, this user can only view/edit/delete precedents in the selected categories — e.g. confine a Wills-only paralegal away from Power of Attorney templates.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(',')
                    ->color('primary'),

                TextColumn::make('email_verified_at')
                    ->label('Verified')
                    ->dateTime()
                    ->placeholder('Not verified')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
