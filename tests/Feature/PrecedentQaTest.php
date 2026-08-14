<?php

namespace Tests\Feature;

use App\Models\Precedent;
use App\Models\User;
use App\Services\PrecedentQaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class PrecedentQaTest extends TestCase
{
    use RefreshDatabase;

    private function precedent(): Precedent
    {
        Storage::fake('local');
        $word = new PhpWord;
        $section = $word->addSection();
        $section->addText('[[CLAUSE:letter]]');
        $section->addText('Letter for {{answers.client_name}}.');
        $section->addText('[[IF:include_note]]');
        $section->addText('Optional note included.');
        $section->addText('[[/IF]]');
        $section->addText('[[/CLAUSE]]');
        $path = tempnam(sys_get_temp_dir(), 'qa_').'.docx';
        IOFactory::createWriter($word, 'Word2007')->save($path);
        Storage::disk('local')->put('precedents/qa.docx', file_get_contents($path));
        @unlink($path);

        return Precedent::create([
            'title' => 'QA Letter',
            'output_title_template' => 'Letter — {{answers.client_name}}',
            'docx_path' => 'precedents/qa.docx',
            'generator_class' => 'template',
            'questionnaire_fields' => [
                ['name' => 'client_name', 'label' => 'Client', 'type' => 'text', 'required' => true],
                ['name' => 'include_note', 'label' => 'Note', 'type' => 'boolean', 'required' => false],
            ],
            'is_active' => true,
        ]);
    }

    public function test_qa_run_validates_and_executes_passing_scenario(): void
    {
        $user = User::factory()->create();
        $precedent = $this->precedent();
        $precedent->testScenarios()->create([
            'name' => 'Note included',
            'answers' => ['client_name' => 'Jane Doe', 'include_note' => true],
            'expected_title' => 'Letter — Jane Doe',
            'expected_includes' => ['Letter for Jane Doe.', 'Optional note included.'],
            'expected_excludes' => ['Public Trustee'],
        ]);

        $run = app(PrecedentQaService::class)->run($precedent, $user->id);

        $this->assertSame('passed', $run->status);
        $this->assertSame('passed', $run->scenario_results[0]['status']);
        $this->assertSame([], $run->issues);
        $this->assertDatabaseCount('document_requests', 0);
    }

    public function test_failed_expectation_fails_qa_run_with_clear_result(): void
    {
        $user = User::factory()->create();
        $precedent = $this->precedent();
        $precedent->testScenarios()->create([
            'name' => 'Wrong expectation',
            'answers' => ['client_name' => 'Jane Doe', 'include_note' => false],
            'expected_includes' => ['This sentence does not exist.'],
        ]);

        $run = app(PrecedentQaService::class)->run($precedent, $user->id);

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('Expected output to include', $run->scenario_results[0]['failures'][0]);
    }

    public function test_baseline_comparison_and_stale_detection_track_changes(): void
    {
        $user = User::factory()->create();
        $precedent = $this->precedent();
        $precedent->testScenarios()->create([
            'name' => 'Basic', 'answers' => ['client_name' => 'Jane Doe', 'include_note' => false],
            'expected_includes' => ['Letter for Jane Doe.'],
        ]);
        $service = app(PrecedentQaService::class);
        $service->setBaseline($precedent, $user->id);
        $firstRun = $service->run($precedent, $user->id);

        $this->assertSame([], $firstRun->comparison);
        $this->assertEquals($firstRun->snapshot, $service->snapshot($firstRun->precedent));
        $this->assertFalse($service->isStale($firstRun), $firstRun->fingerprint.' != '.$service->currentFingerprint($firstRun->precedent));

        $precedent->update(['formatting' => ['profile' => 'legal_modern']]);
        $this->assertTrue($service->isStale($firstRun->fresh()));

        $secondRun = $service->run($precedent->fresh(), $user->id);
        $this->assertTrue(collect($secondRun->comparison)->contains(fn ($change) => $change['field'] === 'formatting'));
    }

    public function test_missing_scenarios_and_template_are_reported(): void
    {
        $user = User::factory()->create();
        $precedent = $this->precedent();
        Storage::disk('local')->delete($precedent->docx_path);

        $run = app(PrecedentQaService::class)->run($precedent, $user->id);
        $codes = collect($run->issues)->pluck('code');

        $this->assertSame('failed', $run->status);
        $this->assertTrue($codes->contains('missing_template'));
        $this->assertTrue($codes->contains('no_scenarios'));
    }
}
