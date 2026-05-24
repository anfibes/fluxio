<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Interpretation provider
    |--------------------------------------------------------------------------
    |
    | Selects which InterpretationProviderInterface implementation feeds the
    | proposal pipeline. The deterministic (rule-based) provider is the default
    | and remains authoritative. The 'ollama' provider is an opt-in sandbox: it
    | routes interpretation through a local LLM but is NOT production-authoritative
    | and changes nothing about the proposal lifecycle. Any unknown value fails
    | explicitly at container-resolution time.
    |
    |   ACTIONS_INTERPRETATION_PROVIDER=deterministic   # default
    |   ACTIONS_INTERPRETATION_PROVIDER=ollama          # opt-in sandbox
    |
    */
    'interpreter' => [
        'provider' => env('ACTIONS_INTERPRETATION_PROVIDER', 'deterministic'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM transport
    |--------------------------------------------------------------------------
    |
    | Configuration for the Actions LLM client abstraction. The client is a
    | generic transport boundary consumed by the opt-in Ollama interpretation
    | provider; the deterministic interpretation provider remains the default.
    |
    */
    'llm' => [
        'provider' => env('ACTIONS_LLM_PROVIDER', 'ollama'),
        'base_url' => env('ACTIONS_LLM_BASE_URL', 'http://127.0.0.1:11434'),
        'model'    => env('ACTIONS_LLM_MODEL', 'qwen3:0.6b'),
        'timeout'  => (int) env('ACTIONS_LLM_TIMEOUT', 10),
    ],
];
