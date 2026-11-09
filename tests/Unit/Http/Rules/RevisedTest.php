<?php

declare(strict_types=1);

namespace Test\Unit\Http\Rules;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Definition\Lifetime;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Http\Rules\Revised;
use Ark4ne\JsonApi\Http\Rules\RulesRevision;
use Closure;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\OtherRevision;
use Test\TestCase;
use Test\Unit\Http\Fixtures\RevisedPlainFormRequest;

final class RevisedTest extends TestCase
{
    public function test_the_head_rules_apply_at_the_head_and_at_every_revision_without_history(): void
    {
        $rules = Revised::rules('required|string');

        self::assertSame('required|string', $rules->at(null));
        self::assertSame('required|string', $rules->at(ApiRevision::Initial));
        self::assertSame('required|string', $rules->at(ApiRevision::UsersReplaceTeachers));
    }

    public function test_a_was_applies_up_to_its_revision_included(): void
    {
        $rules = Revised::rules('max:10')->was(ApiRevision::Labels, rules: 'max:5');

        self::assertSame('max:5', $rules->at(ApiRevision::Initial));
        self::assertSame('max:5', $rules->at(ApiRevision::Labels));
        self::assertSame('max:10', $rules->at(ApiRevision::UsersReplaceTeachers));
        self::assertSame('max:10', $rules->at(null));
    }

    public function test_the_oldest_was_at_least_as_new_as_the_revision_applies_whatever_the_order_of_the_calls(): void
    {
        $rules = Revised::rules('max:30')
            ->was(ApiRevision::Labels, rules: 'max:20')
            ->was(ApiRevision::Initial, rules: 'max:10');

        self::assertSame('max:10', $rules->at(ApiRevision::Initial));
        self::assertSame('max:20', $rules->at(ApiRevision::Labels));
        self::assertSame('max:30', $rules->at(ApiRevision::UsersReplaceTeachers));
    }

    public function test_the_rules_may_be_anything_laravel_accepts_for_a_key(): void
    {
        $closure = static function (string $attribute, mixed $value, Closure $fail): void {};
        $in = Rule::in(['a', 'b']);
        $rules = Revised::rules(['required', $in])->was(ApiRevision::Initial, rules: [$closure]);

        self::assertSame(['required', $in], $rules->at(null));
        self::assertSame([$closure], $rules->at(ApiRevision::Initial));
    }

    public function test_a_key_since_a_revision_exists_from_it_included(): void
    {
        $rules = Revised::rules('string')->since(ApiRevision::Labels);

        self::assertFalse($rules->existsAt(ApiRevision::Initial));
        self::assertNull($rules->at(ApiRevision::Initial));
        self::assertTrue($rules->existsAt(ApiRevision::Labels));
        self::assertSame('string', $rules->at(ApiRevision::Labels));
        self::assertTrue($rules->existsAt(null));
    }

    public function test_a_key_until_a_revision_exists_up_to_it_included_and_not_at_the_head(): void
    {
        $rules = Revised::rules('string')->until(ApiRevision::Labels);

        self::assertTrue($rules->existsAt(ApiRevision::Initial));
        self::assertSame('string', $rules->at(ApiRevision::Labels));
        self::assertFalse($rules->existsAt(ApiRevision::UsersReplaceTeachers));
        self::assertNull($rules->at(ApiRevision::UsersReplaceTeachers));
        self::assertFalse($rules->existsAt(null));
        self::assertNull($rules->at(null));
    }

    public function test_a_key_without_lifetime_exists_everywhere(): void
    {
        $rules = Revised::rules('string');

        self::assertTrue($rules->existsAt(null));
        self::assertTrue($rules->existsAt(ApiRevision::Initial));
    }

    public function test_since_and_until_may_be_the_same_revision(): void
    {
        $rules = Revised::rules('string')->since(ApiRevision::Labels)->until(ApiRevision::Labels);

        self::assertSame([false, true, false, false], [
            $rules->existsAt(ApiRevision::Initial),
            $rules->existsAt(ApiRevision::Labels),
            $rules->existsAt(ApiRevision::UsersReplaceTeachers),
            $rules->existsAt(null),
        ]);
    }

    public function test_the_getters_give_the_head_the_lifetime_and_the_history_oldest_first(): void
    {
        $rules = Revised::rules('max:30')
            ->until(ApiRevision::UsersReplaceTeachers)
            ->was(ApiRevision::Labels, rules: 'max:20')
            ->since(ApiRevision::Initial)
            ->was(ApiRevision::Initial, rules: 'max:10');

        self::assertSame('max:30', $rules->head());
        self::assertEquals(new Lifetime(ApiRevision::Initial, ApiRevision::UsersReplaceTeachers), $rules->lifetime());
        self::assertEquals([
            new RulesRevision(ApiRevision::Initial, 'max:10'),
            new RulesRevision(ApiRevision::Labels, 'max:20'),
        ], $rules->history());
    }

    public function test_the_head_is_given_whatever_the_lifetime(): void
    {
        self::assertSame('string', Revised::rules('string')->until(ApiRevision::Initial)->head());
    }

    public function test_a_new_rules_has_no_history_nor_lifetime(): void
    {
        $rules = Revised::rules('string');

        self::assertSame([], $rules->history());
        self::assertEquals(new Lifetime(), $rules->lifetime());
    }

    public function test_the_declarations_return_new_instances(): void
    {
        $head = Revised::rules('string');
        $head->was(ApiRevision::Initial, rules: 'integer');
        $head->since(ApiRevision::Labels);
        $head->until(ApiRevision::Labels);

        self::assertSame([], $head->history());
        self::assertEquals(new Lifetime(), $head->lifetime());
    }

    public function test_a_since_newer_than_the_until_throws(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('Revised rules exist since [' . ApiRevision::class . '::Labels] and until [' . ApiRevision::class . '::Initial]');

        Revised::rules('string')->until(ApiRevision::Initial)->since(ApiRevision::Labels);
    }

    public function test_an_until_older_than_the_since_throws(): void
    {
        $this->expectException(DefinitionException::class);

        Revised::rules('string')->since(ApiRevision::Labels)->until(ApiRevision::Initial);
    }

    public function test_the_same_was_twice_throws(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('declare was(' . ApiRevision::class . '::Initial) twice');

        Revised::rules('string')->was(ApiRevision::Initial, rules: 'a')->was(ApiRevision::Initial, rules: 'b');
    }

    public function test_a_was_older_than_the_since_throws(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('declare was(' . ApiRevision::class . '::Initial) outside their lifetime');

        Revised::rules('string')->since(ApiRevision::Labels)->was(ApiRevision::Initial, rules: 'integer');
    }

    public function test_a_was_newer_than_the_until_throws(): void
    {
        $this->expectException(DefinitionException::class);

        Revised::rules('string')->until(ApiRevision::Initial)->was(ApiRevision::Labels, rules: 'integer');
    }

    public function test_a_since_declared_after_a_was_it_leaves_out_throws(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('outside their lifetime');

        Revised::rules('string')->was(ApiRevision::Initial, rules: 'integer')->since(ApiRevision::Labels);
    }

    public function test_an_until_declared_after_a_was_it_leaves_out_throws(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('outside their lifetime');

        Revised::rules('string')->was(ApiRevision::Labels, rules: 'integer')->until(ApiRevision::Initial);
    }

    public function test_a_was_on_the_bounds_of_the_lifetime_is_accepted(): void
    {
        $rules = Revised::rules('string')
            ->since(ApiRevision::Initial)
            ->until(ApiRevision::Labels)
            ->was(ApiRevision::Initial, rules: 'a')
            ->was(ApiRevision::Labels, rules: 'b');

        self::assertSame('a', $rules->at(ApiRevision::Initial));
        self::assertSame('b', $rules->at(ApiRevision::Labels));
    }

    public function test_revisions_of_two_enums_throw(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('Revisions of two enums cannot be compared');

        Revised::rules('string')->was(ApiRevision::Initial, rules: 'a')->was(OtherRevision::First, rules: 'b');
    }

    public function test_a_lifetime_of_two_enums_throws(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('Revisions of two enums cannot be compared');

        Revised::rules('string')->since(ApiRevision::Initial)->until(OtherRevision::Labels);
    }

    public function test_reading_at_a_revision_of_another_enum_throws(): void
    {
        $this->expectException(DefinitionException::class);

        Revised::rules('string')->was(ApiRevision::Initial, rules: 'a')->at(OtherRevision::First);
    }

    public function test_resolve_replaces_each_revised_and_keeps_the_other_rules_in_their_order(): void
    {
        $rules = [
            'a' => 'required',
            'b' => Revised::rules('max:10')->was(ApiRevision::Initial, rules: 'max:5'),
            'c' => ['string'],
        ];

        self::assertSame(['a' => 'required', 'b' => 'max:5', 'c' => ['string']], Revised::resolve($rules, ApiRevision::Initial));
        self::assertSame(['a' => 'required', 'b' => 'max:10', 'c' => ['string']], Revised::resolve($rules, null));
    }

    public function test_resolve_leaves_out_the_keys_absent_at_the_revision(): void
    {
        $rules = [
            'added' => Revised::rules('string')->since(ApiRevision::Labels),
            'removed' => Revised::rules('string')->until(ApiRevision::Initial),
            'kept' => 'string',
        ];

        self::assertSame(['removed' => 'string', 'kept' => 'string'], Revised::resolve($rules, ApiRevision::Initial));
        self::assertSame(['added' => 'string', 'kept' => 'string'], Revised::resolve($rules, ApiRevision::Labels));
        self::assertSame(['added' => 'string', 'kept' => 'string'], Revised::resolve($rules, null));
    }

    public function test_resolve_handles_wildcard_and_nested_keys_as_any_key(): void
    {
        $rules = [
            'data.attributes.tags' => 'array',
            'data.attributes.tags.*' => Revised::rules('string')->was(ApiRevision::Initial, rules: 'integer'),
        ];

        self::assertSame(['data.attributes.tags' => 'array', 'data.attributes.tags.*' => 'integer'], Revised::resolve($rules, ApiRevision::Initial));
    }

    public function test_a_plain_form_request_resolves_its_rules_at_the_revision_of_the_request(): void
    {
        $initial = RevisedPlainFormRequest::create('/', 'POST', ['label' => 'Label']);
        $initial->attributes->set(JsonApiContext::REVISION, ApiRevision::Initial);

        self::assertSame(['label' => 'Label'], $this->validate($initial)->validated());
        self::assertSame(['name' => 'Name'], $this->validate(RevisedPlainFormRequest::create('/', 'POST', ['name' => 'Name', 'label' => 'Label']))->validated());

        $this->expectException(ValidationException::class);
        $this->validate(RevisedPlainFormRequest::create('/', 'POST', ['label' => 'Label']));
    }

    private function validate(RevisedPlainFormRequest $request): RevisedPlainFormRequest
    {
        $app = $this->app;
        self::assertNotNull($app);

        $request->setContainer($app)->setRedirector($app->make(Redirector::class));
        $request->validateResolved();

        return $request;
    }
}
