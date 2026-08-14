<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Services\Generators\GeneratorRegistry;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpWord\PhpWord;

/**
 * Renders a document from the wizard's current (possibly partial, never
 * submitted) form state, with zero persisted side effects — no
 * DocumentRequest/DocumentRequestParty row survives the call, regardless of
 * whether generation succeeds or throws.
 *
 * Every generator resolves its party-group data via
 * PartyGroupAssembler::forRequest(), which queries $documentRequest->parties()
 * bound to the model's real primary key — there is no array-based entry
 * point, so an unsaved DocumentRequest can't be fed straight in. Instead we
 * insert real rows inside a transaction, generate against them exactly like
 * the real submit path does, capture the result into plain PHP/PhpWord
 * values, then unconditionally roll back before returning — so those rows
 * never actually persist.
 *
 * Deliberately explicit beginTransaction()/rollBack() rather than
 * DB::transaction(fn () => ...): that helper COMMITS on a normal return,
 * which is exactly the one thing this method must never do. The `finally`
 * block rolls back on both the happy path and any thrown exception; the
 * `return` inside `try` evaluates before `finally` runs, so the already-built
 * $draft/PhpWord (plain PHP values, not DB-backed) survive the rollback
 * untouched — only the rows disappear.
 */
class DocumentPreviewBuilder
{
    public function __construct(
        private readonly DocumentRequestPartyPersister $partyPersister,
        private readonly GeneratorRegistry $registry,
        private readonly DocxBuilder $docxBuilder,
    ) {}

    /**
     * @param  array{client_id?: mixed, precedent_id?: mixed, case_reference?: mixed, answers?: array, parties?: array}  $formState
     * @return array{title: string, phpWord: PhpWord}
     *
     * @throws \RuntimeException if precedent_id is missing/invalid
     */
    public function build(array $formState): array
    {
        $precedent = Precedent::find($formState['precedent_id'] ?? null);

        if (! $precedent) {
            throw new \RuntimeException('Select a precedent first.');
        }

        DB::beginTransaction();

        try {
            $documentRequest = DocumentRequest::create([
                'precedent_id' => $precedent->id,
                'precedent_title_snapshot' => $precedent->title,
                'precedent_jurisdiction_snapshot' => $precedent->jurisdiction,
                'client_id' => $formState['client_id'] ?? null,
                'requested_by' => auth()->id(),
                'case_reference' => $formState['case_reference'] ?? null,
                'answers' => $formState['answers'] ?? [],
                'status' => 'pending',
            ]);

            $this->partyPersister->persist($documentRequest, $precedent, $formState['parties'] ?? []);

            $draft = $this->registry->resolve($precedent->generator_class)->generate($documentRequest);

            return [
                'title' => $draft['title'],
                'phpWord' => $this->docxBuilder->build($draft['title'], $draft['blocks'], $precedent->formattingConfig()),
            ];
        } finally {
            DB::rollBack();
        }
    }
}
