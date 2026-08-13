<?php

namespace App\Contracts;

/**
 * Optional companion to DocumentGenerator — a generator implements this to
 * publish the exact set of [[IF:...]] flags and [[REPEAT:...]]/{{...}}
 * party groups its markers are allowed to reference. Two consumers:
 *   1. Precedent's save-time validation (see Precedent::booted()) cross-checks
 *      every marker/placeholder an admin typed against this list, catching a
 *      typo ("has_alternat_executor") at upload time instead of at first
 *      real generation.
 *   2. PrecedentResource shows this list as read-only authoring reference
 *      next to the file upload, since an admin can't read the generator's
 *      PHP source to know what's valid to type.
 */
interface DeclaresPartyFlags
{
    /** @return array<string, string> flag key => human description */
    public function availableFlags(): array;

    /** @return array<string, string> party_groups key => description */
    public function availableGroups(): array;
}
