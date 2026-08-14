<?php

namespace App\Support;

use App\Models\Precedent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Row-level validation for the CSV Batch Document Generation feature: one
 * CSV row = one document's answers. Distinct from FieldCsvRowValidator,
 * which validates field *definitions* (schema authoring); this validates
 * answer *values* against a precedent's already-declared
 * questionnaireFieldsConfig()/partyGroupsConfig() schema and coerces them
 * into the exact shape DocumentRequest::create()/DocumentRequestPartyPersister
 * expect. Still reuses FieldCsvRowValidator's readCsv()/isBlankRow()/
 * rowToAssoc() for the mechanical CSV parsing.
 *
 * Column convention: bare field name (e.g. "testator_name") for a
 * top-level questionnaire answer; "{group_key}.{n}.{field_name}" (e.g.
 * "beneficiaries.1.name") for a 1-based party-group row slot; "case_reference"
 * / "client_id" are reserved. A party slot counts as "occupied" if any of
 * its fields are non-blank in a given row — that's how one file serves
 * documents needing different numbers of party rows without ragged
 * optional-column schemes.
 */
class BatchCsvRowValidator
{
    public const MAX_ROWS = 200;

    public const RESERVED_COLUMNS = ['case_reference', 'client_id'];

    private const PARTY_COLUMN_RE = '/^([a-z][a-z0-9_]*)\.(\d+)\.([a-z][a-z0-9_]*)$/';

    /**
     * @param  array<int, string>  $header  lowercased/trimmed, as returned by FieldCsvRowValidator::readCsv()
     * @return array{columns: array<string, array<string, mixed>>, errors: array<int, string>}
     */
    public static function parseHeader(array $header, Precedent $precedent): array
    {
        $questionnaireFields = $precedent->questionnaireFieldsConfig();
        $partyGroups = collect($precedent->partyGroupsConfig())->keyBy('key');

        $columns = [];
        $errors = [];

        foreach ($header as $col) {
            if ($col === '') {
                continue;
            }

            if (in_array($col, self::RESERVED_COLUMNS, true)) {
                $columns[$col] = ['type' => 'reserved', 'key' => $col];

                continue;
            }

            if (preg_match(self::PARTY_COLUMN_RE, $col, $m)) {
                [, $groupKey, $slot, $fieldName] = $m;
                $slot = (int) $slot;
                $group = $partyGroups->get($groupKey);

                if (! $group) {
                    $errors[] = "Unknown column \"{$col}\" — \"{$groupKey}\" is not a party group on this precedent.";

                    continue;
                }

                if ($slot < 1 || ($group['max_items'] !== null && $slot > $group['max_items'])) {
                    $max = $group['max_items'] ?? 'unlimited';
                    $errors[] = "Unknown column \"{$col}\" — slot {$slot} is out of range for \"{$groupKey}\" (max {$max}).";

                    continue;
                }

                $fieldDef = collect($group['fields'])->firstWhere('name', $fieldName);

                if (! $fieldDef) {
                    $errors[] = "Unknown column \"{$col}\" — \"{$fieldName}\" is not a field on party group \"{$groupKey}\".";

                    continue;
                }

                $columns[$col] = ['type' => 'party', 'group' => $groupKey, 'slot' => $slot, 'field' => $fieldDef];

                continue;
            }

            if (array_key_exists($col, $questionnaireFields)) {
                $columns[$col] = ['type' => 'answer', 'name' => $col, 'field' => $questionnaireFields[$col]];

                continue;
            }

            $errors[] = "Unknown column \"{$col}\" — not a questionnaire field, client_id/case_reference, "
                .'or a valid group.slot.field party column for this precedent.';
        }

        return ['columns' => $columns, 'errors' => $errors];
    }

    /**
     * @param  array<string, ?string>  $raw  one CSV row, keyed by lowercase header name (FieldCsvRowValidator::rowToAssoc())
     * @param  array<string, array<string, mixed>>  $columns  from parseHeader()
     * @return array{data: ?array, errors: array<int, string>, case_reference: ?string}
     */
    public static function validateRow(array $raw, array $columns, Precedent $precedent, Collection $validClientIds): array
    {
        $errors = [];
        $answers = [];
        $caseReference = null;
        $clientId = null;
        $partyRaw = []; // group => slot => field => raw string

        foreach ($columns as $colName => $col) {
            $value = trim((string) ($raw[$colName] ?? ''));

            if ($col['type'] === 'reserved') {
                if ($col['key'] === 'case_reference') {
                    $caseReference = $value !== '' ? $value : null;
                } elseif ($value !== '') {
                    if (! ctype_digit($value) || ! $validClientIds->contains((int) $value)) {
                        $errors[] = "client_id \"{$value}\" does not match an existing client";
                    } else {
                        $clientId = (int) $value;
                    }
                }

                continue;
            }

            if ($col['type'] === 'answer') {
                $result = self::coerceValue($value, $col['field']);

                if ($result['error'] !== null) {
                    $errors[] = $result['error'];
                } elseif (! $result['omit']) {
                    $answers[$col['name']] = $result['value'];
                }

                continue;
            }

            // Party column — collect raw values first so occupancy (any
            // non-blank field in the slot) can be judged before coercion.
            $partyRaw[$col['group']][$col['slot']][$col['field']['name']] = $value;
        }

        $parties = [];

        foreach ($precedent->partyGroupsConfig() as $group) {
            $slots = $partyRaw[$group['key']] ?? [];
            ksort($slots);

            $rows = [];

            foreach ($slots as $slotIndex => $fields) {
                $occupied = collect($fields)->contains(fn ($v) => trim((string) $v) !== '');
                if (! $occupied) {
                    continue;
                }

                $rowFields = [];
                foreach ($group['fields'] as $fieldDef) {
                    if (! array_key_exists($fieldDef['name'], $fields)) {
                        continue; // this precedent's CSV doesn't map this field for this group
                    }

                    $result = self::coerceValue($fields[$fieldDef['name']], $fieldDef);

                    if ($result['error'] !== null) {
                        $errors[] = "{$group['label']} row {$slotIndex}: {$result['error']}";
                    } elseif (! $result['omit']) {
                        $rowFields[$fieldDef['name']] = $result['value'];
                    }
                }

                $rows[] = $rowFields;
            }

            $count = count($rows);
            if ($count < $group['min_items']) {
                $errors[] = "{$group['label']}: {$count} row(s) provided, minimum is {$group['min_items']}.";
            }
            if ($group['max_items'] !== null && $count > $group['max_items']) {
                $errors[] = "{$group['label']}: {$count} row(s) provided, maximum is {$group['max_items']}.";
            }

            if ($rows !== []) {
                $parties[$group['key']] = $rows;
            }
        }

        if ($errors !== []) {
            return ['data' => null, 'errors' => $errors, 'case_reference' => $caseReference];
        }

        return [
            'data' => [
                'answers' => $answers,
                'parties' => $parties,
                'case_reference' => $caseReference,
                'client_id' => $clientId,
            ],
            'errors' => [],
            'case_reference' => $caseReference,
        ];
    }

    /**
     * Header-only template columns for the "Download CSV Template" action —
     * shares the exact same naming convention as parseHeader()/validateRow()
     * so the template and the validator can never drift apart.
     *
     * @return array<int, string>
     */
    public static function buildTemplateHeader(Precedent $precedent): array
    {
        $columns = ['case_reference', 'client_id'];

        foreach (array_keys($precedent->questionnaireFieldsConfig()) as $name) {
            $columns[] = $name;
        }

        foreach ($precedent->partyGroupsConfig() as $group) {
            $base = max((int) $group['min_items'], 1);
            $target = $base + 2;
            if ($group['max_items'] !== null) {
                $target = min($target, $group['max_items']);
            }
            $target = max($target, $base);

            for ($n = 1; $n <= $target; $n++) {
                foreach ($group['fields'] as $field) {
                    $columns[] = "{$group['key']}.{$n}.{$field['name']}";
                }
            }
        }

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $fieldDef  {label, type, required, options, ...}
     * @return array{value: mixed, error: ?string, omit: bool}
     */
    private static function coerceValue(string $raw, array $fieldDef): array
    {
        $label = $fieldDef['label'] ?? $fieldDef['name'] ?? 'value';
        $required = (bool) ($fieldDef['required'] ?? false);

        if ($raw === '') {
            return $required
                ? ['value' => null, 'error' => "{$label} is required", 'omit' => true]
                : ['value' => null, 'error' => null, 'omit' => true];
        }

        switch ($fieldDef['type'] ?? 'text') {
            case 'number':
                if (! is_numeric($raw)) {
                    return ['value' => null, 'error' => "{$label} must be a number", 'omit' => true];
                }
                $value = (string) (int) $raw === $raw ? (int) $raw : (float) $raw;

                return ['value' => $value, 'error' => null, 'omit' => false];

            case 'date':
                try {
                    $date = Carbon::createFromFormat('Y-m-d', $raw);
                } catch (\Throwable) {
                    $date = null;
                }
                if (! $date || $date->format('Y-m-d') !== $raw) {
                    return ['value' => null, 'error' => "{$label} is not a valid date (expected YYYY-MM-DD)", 'omit' => true];
                }

                return ['value' => $raw, 'error' => null, 'omit' => false];

            case 'boolean':
                $lower = strtolower($raw);
                if (in_array($lower, ['yes', 'true', '1', 'y'], true)) {
                    return ['value' => true, 'error' => null, 'omit' => false];
                }
                if (in_array($lower, ['no', 'false', '0', 'n'], true)) {
                    return ['value' => false, 'error' => null, 'omit' => false];
                }

                return ['value' => null, 'error' => "{$label} must be yes/no (or true/false)", 'omit' => true];

            case 'select':
                $options = $fieldDef['options'] ?? [];
                if (! array_key_exists($raw, $options)) {
                    return ['value' => null, 'error' => "{$label} must be one of: ".implode(', ', array_keys($options)), 'omit' => true];
                }

                return ['value' => $raw, 'error' => null, 'omit' => false];

            default: // text, textarea
                return ['value' => $raw, 'error' => null, 'omit' => false];
        }
    }
}
