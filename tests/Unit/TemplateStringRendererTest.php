<?php

namespace Tests\Unit;

use App\Exceptions\ClauseTemplateException;
use App\Services\TemplateStringRenderer;
use PHPUnit\Framework\TestCase;

class TemplateStringRendererTest extends TestCase
{
    public function test_renders_multiple_dynamic_title_values(): void
    {
        $result = (new TemplateStringRenderer)->render(
            '{{answers.client_name}} — {{answers.matter_type}}',
            ['answers' => ['client_name' => 'Jordan Lee', 'matter_type' => 'Lease']],
        );

        $this->assertSame('Jordan Lee — Lease', $result);
    }

    public function test_unknown_title_placeholder_fails_loudly(): void
    {
        $this->expectException(ClauseTemplateException::class);

        (new TemplateStringRenderer)->render('{{answers.missing}}', ['answers' => []]);
    }
}
