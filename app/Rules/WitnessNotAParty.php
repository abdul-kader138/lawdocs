<?php

namespace App\Rules;

use App\Models\DocumentRequest;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Flags a witness name that exactly matches a beneficiary/attorney/guardian
 * already recorded on the same request — under NSW succession law a gift to
 * an attesting witness (or their spouse) is void, and an attorney/guardian
 * cannot witness their own appointment. A blunt exact-name-match check, not
 * a legal-eligibility engine (no visibility into "is this the witness's
 * spouse", nicknames, etc.) — deliberately a hard block anyway, since a
 * false positive (two unrelated people sharing a name) costs staff one
 * double-check, while a false negative risks a void gift going unnoticed.
 */
class WitnessNotAParty implements ValidationRule
{
    public function __construct(private readonly DocumentRequest $documentRequest) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $name = mb_strtolower(trim((string) $value));
        if ($name === '') {
            return;
        }

        $partyNames = $this->documentRequest->parties()
            ->get()
            ->pluck('data.name')
            ->filter()
            ->map(fn ($n) => mb_strtolower(trim($n)))
            ->all();

        if (in_array($name, $partyNames, true)) {
            $fail("\"{$value}\" is also listed as a beneficiary/attorney/guardian on this request. A witness generally cannot also be a party to the document — a gift to an attesting witness is void under NSW succession law. Double-check before continuing.");
        }
    }
}
