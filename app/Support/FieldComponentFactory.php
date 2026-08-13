<?php

namespace App\Support;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * Maps one field-definition config (the shape shared by Precedent's
 * questionnaire_fields and party_groups[].fields) into a Filament form
 * component. Extracted out of QuestionnaireFormBuilder so PartyGroupFormBuilder
 * can reuse the exact same type->component mapping instead of duplicating it.
 */
class FieldComponentFactory
{
    public static function forField(string $statePath, array $field): Component
    {
        $component = match ($field['type'] ?? 'text') {
            'textarea' => Textarea::make($statePath)->rows(4),
            'number' => TextInput::make($statePath)->numeric(),
            'date' => DatePicker::make($statePath),
            'boolean' => Toggle::make($statePath),
            'select' => Select::make($statePath)->options($field['options'] ?? [])->native(false),
            default => TextInput::make($statePath),
        };

        return $component
            ->label($field['label'] ?? $statePath)
            ->helperText(($field['description'] ?? null) ?: null)
            ->required((bool) ($field['required'] ?? false));
    }
}
