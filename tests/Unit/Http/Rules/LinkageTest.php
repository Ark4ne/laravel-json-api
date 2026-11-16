<?php

declare(strict_types=1);

namespace Test\Unit\Http\Rules;

use Ark4ne\JsonApi\Http\Rules\Linkage;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

final class LinkageTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function documents(): iterable
    {
        yield 'a linkage' => [['author' => ['data' => ['type' => 'users', 'id' => '1']]], true];
        yield 'a null linkage' => [['author' => ['data' => null]], true];
        yield 'a relationship that is not sent' => [[], true];
        yield 'a null relationship, left to the rules of the relationship' => [['author' => null], true];
        yield 'a relationship without linkage' => [['author' => []], false];
        yield 'a relationship with other members only' => [['author' => ['meta' => ['a' => 1]]], false];
    }

    /**
     * @param array<string, mixed> $document
     */
    #[DataProvider('documents')]
    public function test_a_relationship_sent_holds_its_linkage(array $document, bool $valid): void
    {
        $validator = Validator::make($document, ['author.data' => ['nullable', new Linkage()]]);

        self::assertSame($valid, $validator->passes());
        self::assertSame(
            $valid ? [] : ['author.data' => ['The author.data field must be present, it may be null.']],
            $validator->errors()->toArray(),
        );
    }
}
