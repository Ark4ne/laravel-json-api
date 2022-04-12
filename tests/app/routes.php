<?php

use Illuminate\Support\Facades\Route;
use Test\app\Http\Controllers;

Route::apiResource('user', Controllers\UserController::class);

