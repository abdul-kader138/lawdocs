<?php

namespace App\Services;

/**
 * Resolves an input value + a grammatical role to the correct word — e.g.
 * gender "male" + role "possessive" => "his". The same input can (and does)
 * resolve to different words in different sentences, which is the whole
 * point: this is a pure lookup, not a sentence generator.
 *
 * Extensible without a rewrite: a new category (plurality, tone, etc.) is
 * just another TABLES entry; resolve()'s signature never changes.
 */
class GrammarResolver
{
    private const TABLES = [
        'gender' => [
            'male' => [
                'subject' => 'he', 'object' => 'him', 'possessive' => 'his',
                'possessive_pronoun' => 'his', 'reflexive' => 'himself',
            ],
            'female' => [
                'subject' => 'she', 'object' => 'her', 'possessive' => 'her',
                'possessive_pronoun' => 'hers', 'reflexive' => 'herself',
            ],
            // Fallback for firms/trusts/entities/anything not a natural
            // person — also what an unrecognized or blank input value
            // degrades to, so a data-entry quirk doesn't crash generation.
            'entity' => [
                'subject' => 'it', 'object' => 'it', 'possessive' => 'its',
                'possessive_pronoun' => 'its', 'reflexive' => 'itself',
            ],
        ],
    ];

    public function resolve(string $category, string $value, string $role, bool $capitalize = false): string
    {
        $table = self::TABLES[$category]
            ?? throw new \InvalidArgumentException("Unknown grammar category [{$category}].");

        $entry = $table[strtolower(trim($value))] ?? $table['entity']
            ?? throw new \InvalidArgumentException("No entry or fallback for value [{$value}] in category [{$category}].");

        $word = $entry[strtolower(trim($role))]
            ?? throw new \InvalidArgumentException("Unknown grammatical role [{$role}] for category [{$category}].");

        return $capitalize ? ucfirst($word) : $word;
    }

    /** Convenience wrapper for the common gender-pronoun case. */
    public function pronoun(string $genderValue, string $role, bool $capitalize = false): string
    {
        return $this->resolve('gender', $genderValue, $role, $capitalize);
    }
}
