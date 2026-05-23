<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LLM transport
    |--------------------------------------------------------------------------
    |
    | Configuration for the Actions LLM client abstraction. The client is a
    | generic transport boundary: it is not wired into the proposal runtime
    | yet, and the deterministic interpretation provider remains authoritative.
    |
    */
    'llm' => [
        'provider' => env('ACTIONS_LLM_PROVIDER', 'ollama'),
        'base_url' => env('ACTIONS_LLM_BASE_URL', 'http://127.0.0.1:11434'),
        'model'    => env('ACTIONS_LLM_MODEL', 'qwen3:0.6b'),
        'timeout'  => (int) env('ACTIONS_LLM_TIMEOUT', 10),
    ],
];
