<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Precedent;
use App\Rules\PartySharesSumTo100;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;

/**
 * Maps a Precedent's party_groups config into Filament Repeater components
 * for the answer-collection form — one repeater per declared group (e.g.
 * "beneficiaries", "executors"). Mirrors QuestionnaireFormBuilder's role but
 * for repeatable structured data instead of flat scalar answers.
 *
 * Two group-level toggles (supports_per_stirpes / supports_substitute) each
 * add ONE more field automatically, rather than requiring the admin to
 * declare it by hand in party_groups[].fields — this is what makes those
 * toggles actually do something (previously they were captured in config
 * but never consumed anywhere).
 *
 * When a Client is selected on the wizard, each group also gets an "Import
 * from Contacts" picker that appends matching ClientContact rows straight
 * into the repeater — the other half (besides Precedent::client_field_map)
 * of the "enter a person once, reuse them everywhere" feature.
 */
class PartyGroupFormBuilder
{
    /** Reserved, UI-only field name — never persisted into a party row's data (see CreateDocumentRequest::persistParties()). */
    public const SUBSTITUTE_REF_FIELD = '_substitute_ref';

    /** Reserved, UI-only field name — the multi-select trigger itself, never part of "parties" state. */
    public const IMPORT_CONTACTS_FIELD_PREFIX = '_import_contacts_';

    /**
     * @return Component[]
     */
    public static function components(Precedent $precedent, ?Client $client = null): array
    {
        return collect($precedent->partyGroupsConfig())
            ->flatMap(function (array $group) use ($client) {
                $components = [];

                if ($client && $client->contacts()->exists()) {
                    $components[] = static::importContactsField($group, $client);
                }

                $components[] = static::buildRepeater($group);

                return $components;
            })
            ->values()
            ->all();
    }

    private static function buildRepeater(array $group): Repeater
    {
        $columns = max(1, min(4, count($group['fields'])));

        $itemSchema = [
            Grid::make($columns)->schema(
                collect($group['fields'])
                    ->map(fn (array $field) => FieldComponentFactory::forField($field['name'], $field))
                    ->values()
                    ->all()
            ),
        ];

        if ($group['supports_per_stirpes']) {
            $itemSchema[] = Toggle::make('per_stirpes')
                ->label('Per stirpes substitution')
                ->helperText('If this person does not survive, their share passes to their surviving children in equal shares.');
        }

        if ($group['supports_substitute']) {
            $itemSchema[] = static::substituteRefField($group);
        }

        $repeater = Repeater::make("parties.{$group['key']}")
            ->label($group['label'])
            ->schema($itemSchema)
            ->reorderable()
            ->collapsible()
            ->itemLabel(function (array $state) use ($group): ?string {
                $primaryField = $group['fields'][0]['name'] ?? null;

                return $primaryField && filled($state[$primaryField] ?? null)
                    ? (string) $state[$primaryField]
                    : "New {$group['label']}";
            })
            ->addActionLabel("Add {$group['label']}")
            ->defaultItems(0);

        if ($group['min_items'] > 0) {
            $repeater->minItems($group['min_items']);
        }

        if ($group['max_items'] !== null) {
            $repeater->maxItems($group['max_items']);
        }

        if ($group['share_field'] !== null) {
            $repeater->rule(new PartySharesSumTo100($group['label'], $group['share_field']));
        }

        return $repeater;
    }

    /**
     * A multi-select of the Client's contacts. Picking one or more appends
     * a fully pre-filled row per contact directly into the group's Repeater
     * state — matched by plain field-name equality against
     * ClientContact::toImportableAttributes() (name/relationship/gender/
     * email/phone/address/dob), since this app already keeps party-group
     * field names consistent across precedents. Anything the group declares
     * that a contact has no equivalent for (e.g. "share") is simply left
     * blank for manual entry, same as adding a row by hand.
     */
    private static function importContactsField(array $group, Client $client): Select
    {
        return Select::make(self::IMPORT_CONTACTS_FIELD_PREFIX.$group['key'])
            ->label("Import into {$group['label']} from {$client->name}'s Contacts")
            ->multiple()
            ->options($client->contacts()->pluck('name', 'id'))
            ->helperText('Adds a pre-filled row per person selected — still editable afterwards, and nothing here is required.')
            ->native(false)
            ->dehydrated(false)
            ->afterStateUpdated(function (?array $state, Set $set, Get $get) use ($group) {
                if (empty($state)) {
                    return;
                }

                $fieldNames = collect($group['fields'])->pluck('name')->all();
                $existingRows = $get("parties.{$group['key']}") ?? [];

                foreach (ClientContact::query()->whereIn('id', $state)->get() as $contact) {
                    $attributes = $contact->toImportableAttributes();
                    $row = collect($fieldNames)
                        ->mapWithKeys(fn (string $name) => [$name => $attributes[$name] ?? null])
                        ->filter(fn ($value) => $value !== null)
                        ->all();

                    $existingRows[] = $row;
                }

                $set("parties.{$group['key']}", $existingRows);
                // Clear the picker so already-imported names don't linger as
                // "selected" (re-picking the same contact adds them again,
                // which is intentional — e.g. importing into two groups).
                $set(self::IMPORT_CONTACTS_FIELD_PREFIX.$group['key'], []);
            });
    }

    /**
     * A Select listing every OTHER row currently in this same repeater, by
     * its primary field's text (e.g. a beneficiary's name) — value and
     * label are the same string. This is resolved back to a real
     * substitute_party_id server-side in
     * CreateDocumentRequest::persistParties() by matching that text against
     * the persisted rows' own primary field.
     *
     * Deliberately NOT keyed by Livewire's per-item UUID: Filament's
     * Repeater re-indexes its array to plain sequential keys by the time
     * $data reaches handleRecordCreation() (confirmed empirically — any
     * custom/UUID keys set during editing do not survive dehydration), so a
     * UUID captured at render time is already orphaned by submission time.
     * The primary field's own text is the only value both A) known at
     * options-build time in the browser and B) still present, unchanged,
     * after dehydration.
     *
     * Known limitations, both accepted rather than engineered around: a row
     * can select itself as its own substitute (no reliable way to identify
     * "this item's own key" from inside its own field closure), and two
     * rows sharing the exact same primary-field text are ambiguous (last
     * match wins server-side).
     */
    private static function substituteRefField(array $group): Select
    {
        $primaryField = $group['fields'][0]['name'] ?? null;

        return Select::make(self::SUBSTITUTE_REF_FIELD)
            ->label('Specific Substitute (optional)')
            ->helperText('If unavailable, who acts instead of this person specifically? Leave blank for none.')
            ->options(function (Get $get) use ($primaryField) {
                if (! $primaryField) {
                    return [];
                }

                $names = collect($get('../../'))
                    ->map(fn ($row) => $row[$primaryField] ?? null)
                    ->filter(fn ($label) => filled($label))
                    ->unique()
                    ->values()
                    ->all();

                return array_combine($names, $names);
            })
            ->searchable()
            ->native(false)
            ->columnSpanFull();
    }
}
