<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'app' => 'Permuferia API',
    'version' => '1.0',
    'docs' => '/api/health',
]));
