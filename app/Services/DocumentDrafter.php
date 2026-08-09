<?php

namespace App\Services;

use App\Exceptions\DocumentDraftTruncatedException;
use App\Models\Precedent;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DocumentDrafter
{
    /**
     * Draft a full document tailored to $answers, in the style/structure of
     * $precedent. Returns ['title' => string, 'blocks' => array].
     *
     * @throws DocumentDraftTruncatedException if Claude's output was cut off
     *         before finishing — never returns a partial document silently.
     */
    public function draft(Precedent $precedent, array $answers): array
    {
        $tool     = $this->buildTool();
        $system   = $this->buildSystemPrompt($precedent);
        $messages = [['role' => 'user', 'content' => $this->buildUserMessage($precedent, $answers)]];

        try {
            $response = Http::withHeaders([
                'x-api-key'         => Setting::get('claude_api_key') ?: config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
                // Drafting a full document takes materially longer than a chat
                // reply — wma-bot's ClaudeAgent uses 40s, this needs more room.
                ->timeout(60)
                ->retry(2, 500, function (\Throwable $e) {
                    return $e instanceof \Illuminate\Http\Client\ConnectionException
                        || ($e instanceof \Illuminate\Http\Client\RequestException
                            && in_array($e->response->status(), [429, 500, 502, 503, 529]));
                })
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'       => Setting::get('claude_model') ?: config('services.anthropic.model'),
                    'max_tokens'  => (int) Setting::get('claude_max_tokens', 8192),
                    'temperature' => (float) Setting::get('claude_temperature', 0.2),
                    'system'      => $system,
                    'tools'       => [$tool],
                    'tool_choice' => ['type' => 'tool', 'name' => 'draft_document'],
                    'messages'    => $messages,
                ])->throw()->json();
        } catch (\Throwable $e) {
            Log::error('DocumentDrafter: Claude API call failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        if (($response['stop_reason'] ?? null) === 'max_tokens') {
            throw new DocumentDraftTruncatedException(
                'Draft exceeded the model\'s output limit — increase claude_max_tokens in Settings or shorten the precedent.'
            );
        }

        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'tool_use' && ($block['name'] ?? '') === 'draft_document') {
                return [
                    'title'  => $block['input']['title'] ?? $precedent->title,
                    'blocks' => $block['input']['blocks'] ?? [],
                ];
            }
        }

        throw new \RuntimeException('Claude did not return a draft_document tool call.');
    }

    private function buildTool(): array
    {
        return [
            'name'        => 'draft_document',
            'description' => 'Draft the full text of a tailored legal document, following the structure and clause style of the supplied precedent.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'title'  => ['type' => 'string'],
                    'blocks' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'type'      => ['type' => 'string', 'enum' => ['heading', 'paragraph', 'list_item', 'page_break']],
                                'level'     => ['type' => 'integer', 'description' => '1-4, only for type=heading'],
                                'text'      => ['type' => 'string'],
                                'list_type' => ['type' => 'string', 'enum' => ['bullet', 'number'], 'description' => 'only for type=list_item'],
                                'bold'      => ['type' => 'boolean'],
                                'italic'    => ['type' => 'boolean'],
                            ],
                            'required' => ['type', 'text'],
                        ],
                    ],
                ],
                'required' => ['title', 'blocks'],
            ],
        ];
    }

    private function buildSystemPrompt(Precedent $precedent): string
    {
        $category = $precedent->category ?: 'legal document';

        return <<<PROMPT
        You are a legal drafting assistant helping a solicitor prepare a {$category}.

        You will be given a PRECEDENT (a reference document showing the structure,
        section order, and clause style to follow) and a set of ANSWERS supplied by
        the solicitor for this specific client/matter.

        Rules:
        - Mirror the precedent's structure, section order, and tone. Do not carry
          over the precedent's own sample facts, names, or figures — those belong
          only to the precedent, never to the new document.
        - Use ONLY the information given in the answers. If a fact a standard
          clause would normally need was not supplied, insert a clearly bracketed
          placeholder like "[INFORMATION NOT PROVIDED: executor's address]" —
          NEVER invent or assume a fact that wasn't given.
        - Output the complete document ONLY via the `draft_document` tool call.
          Do not include any other commentary.
        PROMPT;
    }

    private function buildUserMessage(Precedent $precedent, array $answers): string
    {
        $fieldLines = collect($precedent->questionnaireFieldsConfig())
            ->map(function (array $field, string $name) use ($answers) {
                $value = $answers[$name] ?? '(not provided)';

                return "- {$field['label']}: {$value}";
            })
            ->implode("\n");

        $precedentText = $precedent->extracted_text ?: '(no extracted text available)';

        return <<<MESSAGE
        ANSWERS FOR THIS DOCUMENT:
        {$fieldLines}

        --- PRECEDENT (reference structure/style only — do not copy its facts) ---
        {$precedentText}
        --- END PRECEDENT ---

        Draft the tailored document now via the draft_document tool.
        MESSAGE;
    }
}
