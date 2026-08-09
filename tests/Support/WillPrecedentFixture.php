<?php

namespace Tests\Support;

use App\Models\Precedent;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\ListItem as ListItemStyle;

/**
 * Shared real, marker-bearing will precedent builder — used anywhere a test
 * needs an actual Precedent that WillGenerator can successfully generate
 * from (i.e. it must have the 'revocation' and 'executor_powers' clause tags).
 */
trait WillPrecedentFixture
{
    private function makeWillPrecedent(array $overrides = []): Precedent
    {
        Storage::fake('local');

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('[[CLAUSE:revocation]]');
        $section->addText('I revoke all prior wills and testamentary acts made by me.', ['bold' => true]);
        $section->addText('[[/CLAUSE]]');
        $section->addText('[[CLAUSE:executor_powers]]');
        $section->addListItem('exercise any powers given to them by law', 0, null, ListItemStyle::TYPE_NUMBER);
        $section->addListItem('sell by public auction or private sale', 0, null, ListItemStyle::TYPE_NUMBER);
        $section->addText('[[/CLAUSE]]');

        $tmp = tempnam(sys_get_temp_dir(), 'will_precedent_') . '.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        Storage::disk('local')->put('precedents/will.docx', file_get_contents($tmp));
        @unlink($tmp);

        return Precedent::create(array_merge([
            'title'                => 'Last Will and Testament',
            'docx_path'            => 'precedents/will.docx',
            'generator_class'      => 'will',
            'questionnaire_fields' => [],
            'is_active'            => true,
        ], $overrides));
    }

    private function willAnswers(array $overrides = []): array
    {
        return array_merge([
            'testator_name'   => 'Ashley Dewell',
            'testator_street' => '1 First Street',
            'testator_suburb' => 'Sydney',
            'testator_state'  => 'State of New South Wales',
            'testator_gender' => 'male',
            'executor_name'   => 'Alfred Smith',
            'executor_gender' => 'male',
            'beneficiaries'   => "Alfred Smith - 50 - male\nBernadette Smith - 50 - female",
        ], $overrides);
    }
}
