<?php

namespace Test\app\Http\Controllers;

use Illuminate\Routing\Controller;
use Test\app\Http\Resources\UserResource;
use Test\app\Models\User;

class UserController extends Controller
{
    use AsApiController;

    protected function getModelClass(): string
    {
        return User::class;
    }

    protected function getResourceClass(): string
    {
        return UserResource::class;
    }
}
