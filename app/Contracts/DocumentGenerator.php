<?php

namespace App\Contracts;

use App\Models\Precedent;

interface DocumentGenerator
{
    /**
     * @param array<string, mixed> $answers raw questionnaire answers keyed by field name
     * @return array{title: string, blocks: array<int, array<string, mixed>>}
     */
    public function generate(Precedent $precedent, array $answers): array;
}
