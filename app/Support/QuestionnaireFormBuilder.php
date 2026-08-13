<?php

namespace App\Support;

use App\Models\Precedent;
use Filament\Forms\Components\Component;

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
            ->map(fn (array $field, string $name) => FieldComponentFactory::forField("answers.{$name}", $field))
            ->values()
            ->all();
    }
}
