<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Aggregate validation across every row of a party-group Repeater — Filament
 * repeaters don't support cross-row rules declaratively, so this operates on
 * the whole array state at once rather than per-item.
 */
class PartySharesSumTo100 implements ValidationRule
{
    private const TOLERANCE = 0.01;

    public function __construct(
        private readonly string $groupLabel,
        private readonly string $shareField,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $rows = is_array($value) ? $value : [];

        if ($rows === []) {
            return;
        }

        $sum = collect($rows)->sum(fn ($row) => (float) ($row[$this->shareField] ?? 0));

        if (abs($sum - 100.0) > self::TOLERANCE) {
            $fail("{$this->groupLabel} shares must sum to 100% (currently {$sum}%).");
        }
    }
}
