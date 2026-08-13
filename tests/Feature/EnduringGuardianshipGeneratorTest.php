<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Models\User;
use App\Services\Generators\EnduringGuardianshipGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EnduringGuardianshipPrecedentFixture;
use Tests\TestCase;

class EnduringGuardianshipGeneratorTest extends TestCase
{
    use EnduringGuardianshipPrecedentFixture;
    use RefreshDatabase;

    private function makeDocumentRequest(Precedent $precedent, array $answers, ?array $guardianRows = null): DocumentRequest
    {
        $documentRequest = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by' => User::factory()->create()->id,
            'answers' => $answers,
            'status' => 'pending',
        ]);

        $this->attachGuardians($documentRequest, $guardianRows);

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

    public function test_generates_expected_title(): void
    {
        $precedent = $this->makeEnduringGuardianshipPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->enduringGuardianshipAnswers());

        $result = app(EnduringGuardianshipGenerator::class)->generate($documentRequest);

        $this->assertSame('Appointment of Enduring Guardian by Ashley Dewell', $result['title']);
    }

    public function test_multiple_guardians_are_each_appointed_via_repeat(): void
    {
        $precedent = $this->makeEnduringGuardianshipPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->enduringGuardianshipAnswers(), [
            ['name' => 'Bernadette Smith', 'address' => 'Addr 1', 'relationship' => 'Spouse'],
            ['name' => 'Charlie Smith', 'address' => 'Addr 2', 'relationship' => 'Child'],
        ]);

        $text = $this->flattenText(app(EnduringGuardianshipGenerator::class)->generate($documentRequest));

        $this->assertStringContainsString('I appoint Bernadette Smith of Addr 1 to be my Enduring Guardian.', $text);
        $this->assertStringContainsString('I appoint Charlie Smith of Addr 2 to be my Enduring Guardian.', $text);
    }

    public function test_joint_flag_selects_jointly_wording(): void
    {
        $precedent = $this->makeEnduringGuardianshipPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->enduringGuardianshipAnswers(['guardians_act_jointly' => true]));

        $text = $this->flattenText(app(EnduringGuardianshipGenerator::class)->generate($documentRequest));

        $this->assertStringContainsString('must act jointly', $text);
        $this->assertStringNotContainsString('jointly and severally', $text);
    }

    public function test_non_joint_flag_selects_severally_wording(): void
    {
        $precedent = $this->makeEnduringGuardianshipPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->enduringGuardianshipAnswers(['guardians_act_jointly' => false]));

        $text = $this->flattenText(app(EnduringGuardianshipGenerator::class)->generate($documentRequest));

        $this->assertStringContainsString('jointly and severally', $text);
    }

    public function test_verbatim_clauses_appear_unparaphrased(): void
    {
        $precedent = $this->makeEnduringGuardianshipPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->enduringGuardianshipAnswers());

        $text = $this->flattenText(app(EnduringGuardianshipGenerator::class)->generate($documentRequest));

        $this->assertStringContainsString('consent to medical, dental, and health care services', $text);
        $this->assertStringContainsString('I revoke all prior appointments of enduring guardian made by me.', $text);
    }
}
