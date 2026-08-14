<?php

namespace Tests\Unit;

use App\Models\Precedent;
use App\Support\BatchCsvRowValidator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class BatchCsvRowValidatorTest extends TestCase
{
    private function precedent(array $overrides = []): Precedent
    {
        return Precedent::make(array_merge([
            'questionnaire_fields' => [
                ['name' => 'testator_name', 'label' => 'Testator Name', 'type' => 'text', 'required' => true],
                ['name' => 'signing_date', 'label' => 'Signing Date', 'type' => 'date', 'required' => false],
                ['name' => 'is_urgent', 'label' => 'Urgent', 'type' => 'boolean', 'required' => false],
                ['name' => 'share_pct', 'label' => 'Share', 'type' => 'number', 'required' => false],
                ['name' => 'gender', 'label' => 'Gender', 'type' => 'select', 'required' => true, 'options' => ['male' => 'Male', 'female' => 'Female']],
            ],
            'party_groups' => [
                [
                    'key' => 'beneficiaries',
                    'label' => 'Beneficiaries',
                    'min_items' => 1,
                    'max_items' => 3,
                    'share_field' => null,
                    'fields' => [
                        ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                        ['name' => 'share', 'label' => 'Share', 'type' => 'number', 'required' => true],
                    ],
                ],
            ],
        ], $overrides));
    }

    private function header(Precedent $precedent, array $extra = []): array
    {
        return BatchCsvRowValidator::parseHeader(
            [...array_keys($precedent->questionnaireFieldsConfig()), 'beneficiaries.1.name', 'beneficiaries.1.share', ...$extra],
            $precedent,
        );
    }

    public function test_parses_known_columns_without_errors(): void
    {
        $precedent = $this->precedent();
        $result = $this->header($precedent);

        $this->assertSame([], $result['errors']);
        $this->assertArrayHasKey('testator_name', $result['columns']);
        $this->assertArrayHasKey('beneficiaries.1.name', $result['columns']);
        $this->assertSame('answer', $result['columns']['testator_name']['type']);
        $this->assertSame('party', $result['columns']['beneficiaries.1.name']['type']);
    }

    public function test_unknown_column_is_a_header_error(): void
    {
        $precedent = $this->precedent();
        $result = $this->header($precedent, ['not_a_real_field']);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('not_a_real_field', $result['errors'][0]);
    }

    public function test_party_slot_beyond_max_items_is_a_header_error(): void
    {
        $precedent = $this->precedent();
        $result = BatchCsvRowValidator::parseHeader(['beneficiaries.4.name'], $precedent);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('out of range', $result['errors'][0]);
    }

    public function test_unknown_party_group_is_a_header_error(): void
    {
        $precedent = $this->precedent();
        $result = BatchCsvRowValidator::parseHeader(['executors.1.name'], $precedent);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('not a party group', $result['errors'][0]);
    }

    public function test_required_field_blank_is_a_row_error(): void
    {
        $precedent = $this->precedent();
        ['columns' => $columns] = $this->header($precedent);

        $result = BatchCsvRowValidator::validateRow(
            ['testator_name' => '', 'gender' => 'male', 'beneficiaries.1.name' => 'Bob', 'beneficiaries.1.share' => '100'],
            $columns,
            $precedent,
            new Collection,
        );

        $this->assertNull($result['data']);
        $this->assertStringContainsString('Testator Name is required', $result['errors'][0]);
    }

    public function test_number_field_coerces_to_int_or_float(): void
    {
        $precedent = $this->precedent();
        ['columns' => $columns] = $this->header($precedent, ['share_pct']);

        $result = BatchCsvRowValidator::validateRow(
            ['testator_name' => 'Jane', 'gender' => 'male', 'share_pct' => '12.5', 'beneficiaries.1.name' => 'Bob', 'beneficiaries.1.share' => '100'],
            $columns,
            $precedent,
            new Collection,
        );

        $this->assertSame(12.5, $result['data']['answers']['share_pct']);
    }

    public function test_number_field_rejects_non_numeric_value(): void
    {
        $precedent = $this->precedent();
        ['columns' => $columns] = $this->header($precedent, ['share_pct']);

        $result = BatchCsvRowValidator::validateRow(
            ['testator_name' => 'Jane', 'gender' => 'male', 'share_pct' => 'abc', 'beneficiaries.1.name' => 'Bob', 'beneficiaries.1.share' => '100'],
            $columns,
            $precedent,
            new Collection,
        );

        $this->assertNull($result['data']);
        $this->assertStringContainsString('must be a number', $result['errors'][0]);
    }

    public function test_date_field_requires_strict_ymd_format(): void
    {
        $precedent = $this->precedent();
        ['columns' => $columns] = $this->header($precedent, ['signing_date']);

        $bad = BatchCsvRowValidator::validateRow(
            ['testator_name' => 'Jane', 'gender' => 'male', 'signing_date' => '14/08/2026', 'beneficiaries.1.name' => 'Bob', 'beneficiaries.1.share' => '100'],
            $columns,
            $precedent,
            new Collection,
        );
        $this->assertNull($bad['data']);

        $good = BatchCsvRowValidator::validateRow(
            ['testator_name' => 'Jane', 'gender' => 'male', 'signing_date' => '2026-08-14', 'beneficiaries.1.name' => 'Bob', 'beneficiaries.1.share' => '100'],
            $columns,
            $precedent,
            new Collection,
        );
        $this->assertSame('2026-08-14', $good['data']['answers']['signing_date']);
    }

    public function test_boolean_field_accepts_common_tokens_and_omits_when_blank(): void
    {
        $precedent = $this->precedent();
        ['columns' => $columns] = $this->header($precedent, ['is_urgent']);

        $yes = BatchCsvRowValidator::validateRow(
            ['testator_name' => 'Jane', 'gender' => 'male', 'is_urgent' => 'Yes', 'beneficiaries.1.name' => 'Bob', 'beneficiaries.1.share' => '100'],
            $columns,
            $precedent,
            new Collection,
        );
        $this->assertTrue($yes['data']['answers']['is_urgent']);

        $blank = BatchCsvRowValidator::validateRow(
            ['testator_name' => 'Jane', 'gender' => 'male', 'is_urgent' => '', 'beneficiaries.1.name' => 'Bob', 'beneficiaries.1.share' => '100'],
            $columns,
            $precedent,
            new Collection,
        );
        $this->assertArrayNotHasKey('is_urgent', $blank['data']['answers']);
    }

    public function test_select_field_rejects_value_outside_options(): void
    {
        $precedent = $this->precedent();
        ['columns' => $columns] = $this->header($precedent);

        $result = BatchCsvRowValidator::validateRow(
            ['testator_name' => 'Jane', 'gender' => 'nonbinary', 'beneficiaries.1.name' => 'Bob', 'beneficiaries.1.share' => '100'],
            $columns,
            $precedent,
            new Collection,
        );

        $this->assertNull($result['data']);
        $this->assertStringContainsString('must be one of', $result['errors'][0]);
    }

    public function test_party_group_below_min_items_is_a_row_error(): void
    {
        $precedent = $this->precedent();
        ['columns' => $columns] = $this->header($precedent);

        $result = BatchCsvRowValidator::validateRow(
            ['testator_name' => 'Jane', 'gender' => 'male', 'beneficiaries.1.name' => '', 'beneficiaries.1.share' => ''],
            $columns,
            $precedent,
            new Collection,
        );

        $this->assertNull($result['data']);
        $this->assertStringContainsString('minimum is 1', $result['errors'][0]);
    }

    public function test_occupied_party_slot_is_detected_by_any_non_blank_field(): void
    {
        $precedent = $this->precedent();
        $header = BatchCsvRowValidator::parseHeader(
            ['testator_name', 'gender', 'beneficiaries.1.name', 'beneficiaries.1.share', 'beneficiaries.2.name', 'beneficiaries.2.share'],
            $precedent,
        );

        $result = BatchCsvRowValidator::validateRow(
            [
                'testator_name' => 'Jane', 'gender' => 'male',
                'beneficiaries.1.name' => 'Bob', 'beneficiaries.1.share' => '100',
                'beneficiaries.2.name' => '', 'beneficiaries.2.share' => '',
            ],
            $header['columns'],
            $precedent,
            new Collection,
        );

        $this->assertNotNull($result['data']);
        $this->assertCount(1, $result['data']['parties']['beneficiaries']);
    }

    public function test_client_id_must_match_an_existing_client(): void
    {
        $precedent = $this->precedent();
        $header = BatchCsvRowValidator::parseHeader([...array_keys($precedent->questionnaireFieldsConfig()), 'client_id', 'beneficiaries.1.name', 'beneficiaries.1.share'], $precedent);

        $invalid = BatchCsvRowValidator::validateRow(
            ['testator_name' => 'Jane', 'gender' => 'male', 'client_id' => '999', 'beneficiaries.1.name' => 'Bob', 'beneficiaries.1.share' => '100'],
            $header['columns'],
            $precedent,
            new Collection([5, 6, 7]),
        );
        $this->assertNull($invalid['data']);
        $this->assertStringContainsString('does not match an existing client', $invalid['errors'][0]);

        $valid = BatchCsvRowValidator::validateRow(
            ['testator_name' => 'Jane', 'gender' => 'male', 'client_id' => '6', 'beneficiaries.1.name' => 'Bob', 'beneficiaries.1.share' => '100'],
            $header['columns'],
            $precedent,
            new Collection([5, 6, 7]),
        );
        $this->assertSame(6, $valid['data']['client_id']);
    }

    public function test_build_template_header_matches_parseable_columns(): void
    {
        $precedent = $this->precedent();
        $template = BatchCsvRowValidator::buildTemplateHeader($precedent);

        $result = BatchCsvRowValidator::parseHeader($template, $precedent);
        $this->assertSame([], $result['errors'], 'Every generated template column must itself be a recognized column.');
    }
}
