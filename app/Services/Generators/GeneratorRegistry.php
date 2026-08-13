<?php

namespace App\Services\Generators;

use App\Contracts\DocumentGenerator;

/**
 * Maps a short registered KEY (never a raw class-string — safer and more
 * admin-friendly than free-text class-name entry) to a generator class.
 * Adding a new document type is "add one line here + write the class."
 */
class GeneratorRegistry
{
    /** @var array<string, class-string<DocumentGenerator>> */
    private const MAP = [
        'will' => WillGenerator::class,
        'power_of_attorney' => PowerOfAttorneyGenerator::class,
        'enduring_guardianship' => EnduringGuardianshipGenerator::class,
        'costs_agreement' => CostsAgreementGenerator::class,
    ];

    /** @return array<string, string> key => Title Case label, for the admin Select */
    public static function options(): array
    {
        return collect(self::MAP)->keys()
            ->mapWithKeys(fn ($key) => [$key => str($key)->replace('_', ' ')->title()->toString()])
            ->all();
    }

    public function resolve(?string $key): DocumentGenerator
    {
        if ($key === null || ! isset(self::MAP[$key])) {
            throw new \RuntimeException('No document generator registered for key ['.($key ?? 'null').'].');
        }

        return app(self::MAP[$key]);
    }
}
