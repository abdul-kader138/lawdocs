<?php

namespace App\Support;

use App\Models\Precedent;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * Maps a Precedent's questionnaire_fields config into Filament form
 * components. Kept separate from DocumentRequestResource so this
 * config-to-component mapping can be unit-tested on its own.
 */
class QuestionnaireFormBuilder
{
    /**
     * @return Component[]
     */
    public static function components(Precedent $precedent): array
    {
        return collect($precedent->questionnaireFieldsConfig())
            ->map(function (array $field, string $name) {
                $statePath = "answers.{$name}";

                $component = match ($field['type']) {
                    'textarea' => Textarea::make($statePath)->rows(4),
                    'number'   => TextInput::make($statePath)->numeric(),
                    'date'     => DatePicker::make($statePath),
                    'boolean'  => Toggle::make($statePath),
                    'select'   => Select::make($statePath)->options($field['options'] ?? [])->native(false),
                    default    => TextInput::make($statePath),
                };

                return $component
                    ->label($field['label'])
                    ->helperText($field['description'] ?: null)
                    ->required($field['required']);
            })
            ->values()
            ->all();
    }
}
