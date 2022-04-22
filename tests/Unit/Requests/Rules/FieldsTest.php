<?php

namespace Test\Unit\Requests\Rules;

use Ark4ne\JsonApi\Requests\Rules\Fields;
use Test\app\Http\Resources\UserResource;
use Test\TestCase;

class FieldsTest extends TestCase
{
    public function testPasses()
    {
        $rule = new Fields(UserResource::class);

        $this->assertTrue($rule->passes(null, [
            'user' => 'name,email',
            'post' => 'content',
        ]));

        $this->assertFalse($rule->passes(null, [
            'user' => 'name,email',
            'unknown' => 'content',
        ]));

        $this->assertFalse($rule->passes(null, [
            'user' => 'name,unknown',
            'post' => 'content',
        ]));

        $this->assertFalse($rule->passes(null, [
            'user' => 'name,email',
            'post' => 'unknown',
        ]));
    }
}
