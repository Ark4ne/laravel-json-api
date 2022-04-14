<?php

namespace Test\app\Http\Controllers;

use Illuminate\Routing\Controller;
use Test\app\Http\Resources\CommentResource;
use Test\app\Models\Comment;

class CommentController extends Controller
{
    use AsApiController;

    protected function getModelClass(): string
    {
        return Comment::class;
    }

    protected function getResourceClass(): string
    {
        return CommentResource::class;
    }
}
