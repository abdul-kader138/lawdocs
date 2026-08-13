<?php

namespace App\Contracts;

use App\Models\DocumentRequest;

interface DocumentGenerator
{
    /**
     * Takes the whole request (not just Precedent + answers) because a
     * generator may also need the request's structured party data
     * (DocumentRequestParty rows, via PartyGroupAssembler) — DocumentRequest
     * is the one object that already carries precedent, answers, and parties.
     *
     * @return array{title: string, blocks: array<int, array<string, mixed>>}
     */
    public function generate(DocumentRequest $documentRequest): array;
}
