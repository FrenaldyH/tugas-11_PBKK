<?php

use Illuminate\Support\Facades\Route;

// Browser playground for the API (login, prompt, streaming, history)
Route::view('/', 'playground', ['themes' => array_keys(config('llm.themes'))]);
