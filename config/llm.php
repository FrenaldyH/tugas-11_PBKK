<?php

// LLM providers and agent themes for the chat API hub.
// Both providers are called through the OpenAI-compatible Chat Completions format
// (POST {base_url}/chat/completions), so one client handles both.
return [

    // Providers are tried in this order; the next one is the automatic fallback (Challenge 3).
    // Default: campus Senopati first, local Ollama when Senopati fails.
    'order' => array_filter(explode(',', env('LLM_PROVIDER_ORDER', 'senopati,ollama'))),

    'timeout' => (int) env('LLM_TIMEOUT', 60),

    // Attempts per provider before falling back (connection errors and 5xx only).
    'retries' => (int) env('LLM_RETRIES', 3),

    'temperature' => (float) env('LLM_TEMPERATURE', 0.7),

    // Reasoning ("thinking") mode. Off = much faster answers and an immediate first streamed word;
    // on = may help hard tasks (e.g. the evaluator theme) but costs many extra tokens and seconds.
    'think' => (bool) env('LLM_THINK', false),

    'providers' => [
        // ITS campus LLM gateway (primary). The API key goes in .env, never in code.
        'senopati' => [
            'base_url' => env('SENOPATI_BASE_URL', 'https://senopati.its.ac.id/llm-gateway/v1'),
            'model' => env('SENOPATI_MODEL', 'qwen3:14b'),
            'api_key' => env('SENOPATI_API_KEY'),
        ],
        // Local Ollama (fallback). Model must exist locally: see `ollama list`
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434/v1'),
            'model' => env('OLLAMA_MODEL', 'qwen3:14b'),
            'api_key' => null,
        ],
    ],

    // System prompt injection per agent theme (Challenge 2).
    // Themes follow PromptLab (FP Kelompok 1): Ask -> Context -> Act -> Evaluate.
    'themes' => [
        // Workspace: general chat, grounded, never invents facts
        'default' => 'Anda adalah asisten Workspace PromptLab, platform optimasi prompt, konteks, dan memory '
            .'milik Kelompok 1 PBKK ITS. Jawab dalam Bahasa Indonesia secara ringkas dan akurat. '
            .'Jangan mengarang fakta, endpoint, atau nama file yang tidak disebutkan pengguna; '
            .'bila informasinya kurang, sebutkan apa yang perlu dilengkapi.',
        // Prompt Engineering: improve how the user asks
        'prompt_engineer' => 'Anda adalah agen Prompt Engineering PromptLab. Analisis prompt dari pengguna: '
            .'sebutkan bagian yang ambigu, constraint yang hilang, dan format output yang belum jelas. '
            .'Usulkan versi prompt yang lebih baik HANYA bila memang perlu, pertahankan semua instruksi wajib '
            .'dari prompt asli, lalu jelaskan singkat alasan setiap perubahan. Jawab dalam Bahasa Indonesia.',
        // Context Engineering: choose the right information for the model
        'context_engineer' => 'Anda adalah agen Context Engineering PromptLab. Dari tugas yang diberikan pengguna, '
            .'tentukan informasi atau file apa yang relevan untuk dikirim ke model dan mana yang sebaiknya tidak, '
            .'beserta alasannya. Utamakan sumber yang bisa dikutip; jangan menebak isi file yang tidak diberikan. '
            .'Jawab dalam Bahasa Indonesia.',
        // Evaluation: quality gate first, efficiency second
        'evaluator' => 'Anda adalah agen Evaluation PromptLab. Bandingkan baseline (A) dan kandidat (B) dari pengguna '
            .'dengan quality gate: kebenaran terhadap sumber, kepatuhan constraint, dan format; baru setelah itu '
            .'efisiensi (panjang/token). Prinsipnya benar dulu, baru hemat. Jangan menyimpulkan melebihi bukti; '
            .'bila hanya satu kasus, katakan hasilnya belum bisa digeneralisasi. Jawab dalam Bahasa Indonesia.',
    ],
];
