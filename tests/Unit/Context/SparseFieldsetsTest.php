<?php

declare(strict_types=1);

namespace Test\Unit\Context;

use Ark4ne\JsonApi\Context\SparseFieldsets;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SparseFieldsetsTest extends TestCase
{
    public function test_the_fields_of_a_type_are_parsed_once(): void
    {
        $fields = SparseFieldsets::parse(['user' => 'name,email', 'post' => 'title']);

        self::assertSame(['name', 'email'], $fields->of('user'));
        self::assertSame(['title'], $fields->of('post'));
        self::assertNull($fields->of('comment'));
        self::assertTrue($fields->restricts('user'));
        self::assertFalse($fields->restricts('comment'));
    }

    public function test_an_empty_value_restricts_the_type_to_no_field(): void
    {
        $fields = SparseFieldsets::parse(['user' => '']);

        self::assertSame([], $fields->of('user'));
        self::assertFalse($fields->allows('user', 'name'));
    }

    public function test_an_unrestricted_type_allows_every_field_but_requests_none(): void
    {
        $fields = SparseFieldsets::parse(['post' => 'title']);

        self::assertTrue($fields->allows('user', 'name'));
        self::assertFalse($fields->requests('user', 'name'));
    }

    public function test_a_restricted_type_allows_and_requests_only_its_fields(): void
    {
        $fields = SparseFieldsets::parse(['user' => 'name']);

        self::assertTrue($fields->allows('user', 'name'));
        self::assertTrue($fields->requests('user', 'name'));
        self::assertFalse($fields->allows('user', 'email'));
        self::assertFalse($fields->requests('user', 'email'));
    }

    public function test_a_struct_member_requests_its_struct_and_a_struct_its_members(): void
    {
        $fields = SparseFieldsets::parse(['user' => 'address.city,profile']);

        self::assertTrue($fields->allows('user', 'address'));
        self::assertTrue($fields->allows('user', 'address.city'));
        self::assertFalse($fields->allows('user', 'address.street'));
        self::assertTrue($fields->allows('user', 'profile.bio'));
        self::assertTrue($fields->requests('user', 'profile.bio'));
        self::assertFalse($fields->allows('user', 'addressbook'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function restrictingNothing(): array
    {
        return [
            'null' => [null],
            'a string' => ['name'],
            'a list' => [['name']],
        ];
    }

    #[DataProvider('restrictingNothing')]
    public function test_an_invalid_value_restricts_nothing(mixed $fields): void
    {
        $parsed = SparseFieldsets::parse($fields);

        self::assertFalse($parsed->restricts('user'));
        self::assertTrue($parsed->allows('user', 'name'));
    }

    public function test_a_type_whose_value_is_not_a_string_is_not_restricted(): void
    {
        $fields = SparseFieldsets::parse(['user' => ['name'], 'post' => 'title']);

        self::assertFalse($fields->restricts('user'));
        self::assertSame(['title'], $fields->of('post'));
    }

    public function test_empty_segments_are_ignored(): void
    {
        self::assertSame(['name', 'email'], SparseFieldsets::parse(['user' => ',name,,email,'])->of('user'));
    }

    public function test_none_restricts_nothing(): void
    {
        self::assertFalse(SparseFieldsets::none()->restricts('user'));
    }

    public function test_a_numeric_type_is_restricted(): void
    {
        $fields = SparseFieldsets::parse([2024 => 'title']);

        self::assertTrue($fields->restricts('2024'));
        self::assertSame(['title'], $fields->of('2024'));
    }

    public function test_an_empty_value_turned_into_null_by_laravel_restricts_the_type_to_no_field(): void
    {
        // ConvertEmptyStringsToNull turns `fields[user]=` into null.
        $fields = SparseFieldsets::parse(['user' => null]);

        self::assertSame([], $fields->of('user'));
    }

    public function test_every_restricted_type_is_listed_with_its_fields(): void
    {
        self::assertSame(
            ['user' => ['name', 'email'], 'post' => []],
            SparseFieldsets::parse(['user' => ' name,,email ', 'post' => null])->all(),
        );
    }
}
