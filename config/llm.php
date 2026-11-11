<?php

// LLM providers and agent themes for the chat API hub.
// Both providers are called through the OpenAI-compatible Chat Completions format
// (POST {base_url}/chat/completions), so one client handles both.
return [

    // Providers are tried in this order; the next one is the automatic fallback.
    'order' => array_filter(explode(',', env('LLM_PROVIDER_ORDER', 'ollama,senopati'))),

    'timeout' => (int) env('LLM_TIMEOUT', 60),

    // Attempts per provider before falling back (connection errors and 5xx only).
    'retries' => (int) env('LLM_RETRIES', 3),

    'temperature' => (float) env('LLM_TEMPERATURE', 0.7),

    'providers' => [
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434/v1'),
            'model' => env('OLLAMA_MODEL', 'qwen3:14b'),
            'api_key' => null,
        ],
        'senopati' => [
            // ASSUMPTION: Senopati exposes an OpenAI-compatible endpoint; set the real URL from ITS.
            'base_url' => env('SENOPATI_BASE_URL'),
            'model' => env('SENOPATI_MODEL'),
            'api_key' => env('SENOPATI_API_KEY'),
        ],
    ],

    // System prompt injection per agent theme (Challenge 2).
    'themes' => [
        'default' => 'Anda adalah asisten AI ramah dari ITS untuk platform AgentChat. '
            .'Jawab dalam Bahasa Indonesia secara ringkas, akurat, dan jujur bila tidak tahu.',
        'security_scanner' => 'Anda adalah agen siber ITS. Analisis kode atau konfigurasi dari pengguna, '
            .'laporkan setiap kerentanan beserta tingkat risiko (rendah/sedang/tinggi) dan cara memperbaikinya. '
            .'Jawab dalam Bahasa Indonesia.',
        'malware_scanner' => 'Anda adalah analis malware ITS. Periksa skrip, perintah, atau perilaku file dari pengguna, '
            .'jelaskan indikasi berbahaya dan tindakan mitigasi. Jangan pernah menulis kode berbahaya. '
            .'Jawab dalam Bahasa Indonesia.',
        'log_analyzer' => 'Anda adalah analis log server ITS. Baca potongan log dari pengguna, ringkas kejadian penting, '
            .'tandai anomali atau error, dan sarankan langkah investigasi. Jawab dalam Bahasa Indonesia.',
    ],
];
