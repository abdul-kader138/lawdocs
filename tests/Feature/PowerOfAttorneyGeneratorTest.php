<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Models\User;
use App\Services\Generators\PowerOfAttorneyGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PowerOfAttorneyPrecedentFixture;
use Tests\TestCase;

class PowerOfAttorneyGeneratorTest extends TestCase
{
    use PowerOfAttorneyPrecedentFixture;
    use RefreshDatabase;

    private function makeDocumentRequest(Precedent $precedent, array $answers, ?array $attorneyRows = null): DocumentRequest
    {
        $documentRequest = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by' => User::factory()->create()->id,
            'answers' => $answers,
            'status' => 'pending',
        ]);

        $this->attachAttorneys($documentRequest, $attorneyRows);

        return $documentRequest;
    }

    private function flattenText(array $result): string
    {
        $flat = collect($result['blocks'])->pluck('text')->filter()->implode(' | ');
        $raw = collect($result['blocks'])->where('type', 'raw')
            ->flatMap(fn ($b) => collect($b['elements'])->flatMap(fn ($el) => array_column($el->runs, 'text')))
            ->implode(' | ');

        return $flat.' | '.$raw;
    }

    public function test_enduring_power_produces_enduring_title_and_notice(): void
    {
        $precedent = $this->makePowerOfAttorneyPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->powerOfAttorneyAnswers(['is_enduring' => true]));

        $result = app(PowerOfAttorneyGenerator::class)->generate($documentRequest);

        $this->assertSame('Enduring Power of Attorney of Ashley Dewell', $result['title']);
        $this->assertStringContainsString('intended to be an Enduring Power of Attorney', $this->flattenText($result));
    }

    public function test_general_power_omits_enduring_title_and_notice(): void
    {
        $precedent = $this->makePowerOfAttorneyPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->powerOfAttorneyAnswers(['is_enduring' => false]));

        $result = app(PowerOfAttorneyGenerator::class)->generate($documentRequest);

        $this->assertSame('General Power of Attorney of Ashley Dewell', $result['title']);
        $this->assertStringNotContainsString('intended to be an Enduring Power of Attorney', $this->flattenText($result));
    }

    public function test_multiple_attorneys_are_each_appointed_via_repeat(): void
    {
        $precedent = $this->makePowerOfAttorneyPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->powerOfAttorneyAnswers(), [
            ['name' => 'Bernadette Smith', 'address' => 'Addr 1', 'relationship' => 'Spouse'],
            ['name' => 'Charlie Smith', 'address' => 'Addr 2', 'relationship' => 'Child'],
        ]);

        $text = $this->flattenText(app(PowerOfAttorneyGenerator::class)->generate($documentRequest));

        $this->assertStringContainsString('I appoint Bernadette Smith of Addr 1 to be my Attorney.', $text);
        $this->assertStringContainsString('I appoint Charlie Smith of Addr 2 to be my Attorney.', $text);
    }

    public function test_joint_flag_selects_jointly_wording(): void
    {
        $precedent = $this->makePowerOfAttorneyPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->powerOfAttorneyAnswers(['attorneys_act_jointly' => true]));

        $text = $this->flattenText(app(PowerOfAttorneyGenerator::class)->generate($documentRequest));

        $this->assertStringContainsString('must act jointly', $text);
        $this->assertStringNotContainsString('jointly and severally', $text);
    }

    public function test_non_joint_flag_selects_severally_wording(): void
    {
        $precedent = $this->makePowerOfAttorneyPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->powerOfAttorneyAnswers(['attorneys_act_jointly' => false]));

        $text = $this->flattenText(app(PowerOfAttorneyGenerator::class)->generate($documentRequest));

        $this->assertStringContainsString('jointly and severally', $text);
    }

    public function test_verbatim_clauses_appear_unparaphrased(): void
    {
        $precedent = $this->makePowerOfAttorneyPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->powerOfAttorneyAnswers());

        $text = $this->flattenText(app(PowerOfAttorneyGenerator::class)->generate($documentRequest));

        $this->assertStringContainsString('financial and property matters', $text);
        $this->assertStringContainsString('I revoke all prior powers of attorney made by me.', $text);
    }
}
