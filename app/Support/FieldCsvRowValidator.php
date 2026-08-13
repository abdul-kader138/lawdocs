<?php

namespace App\Support;

/**
 * Row-level validation shared by every CSV bulk-field importer in the admin
 * panel (Questionnaire Fields, Party Group row fields) — both target fields
 * of the identical {name, label, type, required, description, options}
 * shape (see Precedent::questionnaireFieldsConfig()/partyGroupsConfig()),
 * so the validation rules must stay identical and are kept in one place
 * rather than duplicated per importer.
 */
class FieldCsvRowValidator
{
    /** Same enum the Filament "Input type" Select offers for both Questionnaire Fields and Party Group row fields. */
    public const VALID_TYPES = ['text', 'textarea', 'number', 'date', 'boolean', 'select'];

    /** @return array{header: array<int, string>, rows: array<int, array<int, ?string>>} */
    public static function readCsv(string $realPath): array
    {
        $rows = array_map('str_getcsv', file($realPath));
        $header = array_map(fn ($h) => strtolower(trim($h)), array_shift($rows) ?? []);

        return ['header' => $header, 'rows' => $rows];
    }

    /** @param array<int, ?string> $row @return array<string, ?string> */
    public static function rowToAssoc(array $header, array $row): array
    {
        return array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));
    }

    public static function isBlankRow(array $row): bool
    {
        return $row === [null] || $row === false;
    }

    /**
     * @param  array<string, ?string>  $raw  one CSV row, keyed by lowercase header name
     * @param  bool  $hasRequiredColumn  whether the CSV header included a "required" column at all
     * @return array{field: ?array, errors: array<int, string>}
     */
    public static function validate(array $raw, bool $hasRequiredColumn): array
    {
        $name = trim((string) ($raw['name'] ?? ''));
        $label = trim((string) ($raw['label'] ?? ''));
        $type = strtolower(trim((string) ($raw['type'] ?? '')));
        $description = trim((string) ($raw['description'] ?? ''));

        $errors = [];
        if ($name === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $name) || strlen($name) > 50) {
            $errors[] = 'name must be lowercase letters/numbers/underscores, starting with a letter, max 50 chars';
        }
        if ($label === '' || strlen($label) > 100) {
            $errors[] = 'label is required, max 100 chars';
        }
        if (! in_array($type, self::VALID_TYPES, true)) {
            $errors[] = 'type must be one of: '.implode(', ', self::VALID_TYPES);
        }
        if ($description === '' || strlen($description) > 300) {
            $errors[] = 'description is required, max 300 chars';
        }

        if ($errors !== []) {
            return ['field' => null, 'errors' => $errors];
        }

        $required = $hasRequiredColumn
            ? in_array(strtolower(trim((string) ($raw['required'] ?? ''))), ['1', 'true', 'yes'], true)
            : true;

        $options = [];
        if ($type === 'select' && filled($raw['options'] ?? null)) {
            foreach (explode('|', (string) $raw['options']) as $pair) {
                [$key, $optionLabel] = array_pad(explode('=', $pair, 2), 2, null);
                if (filled($key)) {
                    $options[trim($key)] = trim((string) $optionLabel);
                }
            }
        }

        return [
            'field' => [
                'name' => $name,
                'label' => $label,
                'type' => $type,
                'required' => $required,
                'description' => $description,
                'options' => $options,
            ],
            'errors' => [],
        ];
    }
}
