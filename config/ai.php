<?php

use Alpha\Journal\Enums\AiFeature;

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    | Installation-wide default, overridable from Admin → AI. A journal's own
    | ai_enabled setting sits below this: both must be on for a call to leave
    | the building.
    */
    'enabled' => env('AI_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Per-journal default
    |--------------------------------------------------------------------------
    | CLAUDE.md §6 guardrail 2 — AI here processes UNPUBLISHED manuscripts, which
    | are confidential. Opt-in is the posture a university procurement review
    | expects, so a journal nobody has configured does not get processed.
    */
    'journal_default' => env('AI_JOURNAL_DEFAULT', false),

    /*
    |--------------------------------------------------------------------------
    | Model routing (§6 guardrail 5)
    |--------------------------------------------------------------------------
    | The model id also selects the provider: claude-* goes to Anthropic,
    | gemini-* to Google (see ProviderRegistry). One value per feature, so a
    | provider and a model can never be configured into disagreeing.
    |
    | Haiku/Flash for extraction, classification and triage — anything running on
    | every submission. Sonnet/Pro for what a human will read closely.
    */
    'models' => [
        AiFeature::MetadataExtraction->value => env('AI_MODEL_EXTRACTION', 'claude-haiku-4-5-20251001'),
    ],

    'default_model' => env('AI_MODEL_DEFAULT', 'claude-haiku-4-5-20251001'),
    'default_gemini_model' => env('AI_MODEL_GEMINI_DEFAULT', 'gemini-2.5-flash'),

    /*
    |--------------------------------------------------------------------------
    | Pricing, in microcents per token
    |--------------------------------------------------------------------------
    | Fills ai_runs.cost_microcents. Integer microcents keeps money out of
    | floating point.
    |
    | The conversion, spelled out because getting it wrong by a factor of 100 is
    | easy and silent: 1 cent = 10,000 microcents, so $1 per MTok works out to
    | exactly 1 microcent per token ($1 = 1,000,000 microcents / 1,000,000 tokens).
    |
    | ⚠ VERIFY THESE AGAINST CURRENT PUBLISHED PRICING BEFORE BILLING ANYONE.
    | Vendor prices change and these were set from figures that may already be
    | stale. They affect only reported cost, never behaviour — a wrong number
    | here produces a wrong invoice, not a wrong extraction.
    */
    'pricing' => [
        // Anthropic — $/MTok in / out: Haiku 4.5 1/5, Sonnet 3/15.
        'claude-haiku-4-5-20251001' => ['input' => 1, 'output' => 5],
        'claude-sonnet-5' => ['input' => 3, 'output' => 15],

        // Google — Gemini 2.5 Flash 0.30/2.50, Pro 1.25/10.
        'gemini-2.5-flash' => ['input' => 0.3, 'output' => 2.5],
        'gemini-2.5-pro' => ['input' => 1.25, 'output' => 10],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request shape
    |--------------------------------------------------------------------------
    | max_input_chars: only the manuscript front matter is sent. Title, abstract,
    | authors, affiliations and keywords all live there, so this is both
    | sufficient for A1 and the main reason a run costs a fraction of a cent.
    | (Reference extraction needs the tail — that is A2/A5, not this.)
    */
    'max_input_chars' => env('AI_MAX_INPUT_CHARS', 15000),
    'timeout' => env('AI_TIMEOUT', 60),
    'retries' => env('AI_RETRIES', 2),

];
