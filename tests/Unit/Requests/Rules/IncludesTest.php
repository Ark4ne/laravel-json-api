<?php

namespace Test\Unit\Requests\Rules;

use Ark4ne\JsonApi\Requests\Rules\Includes;
use Test\app\Http\Resources\UserResource;
use Test\TestCase;

class IncludesTest extends TestCase
{
    public function testPasses()
    {
        $rule = new Includes(UserResource::class);

        $this->assertFalse($rule->passes(null, [
            'posts',
            'posts.user',
            'posts.user.comments',
            'posts.user.posts'
        ]));

        $this->assertTrue($rule->passes(null, implode(',', [
            'posts',
            'posts.user',
            'posts.user.comments',
            'posts.user.posts'
        ])));

        $this->assertFalse($rule->passes(null, implode(',', [
            'posts',
            'unknown',
        ])));

        $this->assertFalse($rule->passes(null, implode(',', [
            'posts.unknown',
        ])));
    }
}
