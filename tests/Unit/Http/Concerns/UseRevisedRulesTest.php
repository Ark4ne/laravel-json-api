<?php

declare(strict_types=1);

namespace Test\Unit\Http\Concerns;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Test\app\Http\Middleware\UseApiRevision;
use Test\app\Revisions\ApiRevision;
use Test\TestCase;
use Test\Unit\Http\Fixtures\RevisedTraitFormRequest;

final class UseRevisedRulesTest extends TestCase
{
    public function test_the_rules_are_resolved_at_the_revision_of_the_request(): void
    {
        $request = RevisedTraitFormRequest::create('/', 'POST', ['label' => 'Label', 'name' => 'Name', 'note' => 'Note']);
        $request->attributes->set(JsonApiContext::REVISION, ApiRevision::Initial);

        self::assertSame(['label' => 'Label', 'note' => 'Note'], $this->validate($request)->validated());
    }

    public function test_the_head_rules_apply_without_revision(): void
    {
        $request = RevisedTraitFormRequest::create('/', 'POST', ['label' => 'Label', 'name' => 'Name']);

        self::assertSame(['name' => 'Name'], $this->validate($request)->validated());
    }

    public function test_a_key_of_the_revision_is_required_there(): void
    {
        $request = RevisedTraitFormRequest::create('/', 'POST', ['label' => 'Label']);

        try {
            $this->validate($request);
            self::fail('The name is required at the head.');
        } catch (ValidationException $e) {
            self::assertSame(['name'], array_keys($e->errors()));
        }
    }

    public function test_the_revision_set_by_a_middleware_reaches_the_form_request(): void
    {
        Route::post('trait', static fn(RevisedTraitFormRequest $request): array => $request->validated())
            ->middleware(UseApiRevision::class);

        $this->postJson('trait', ['label' => 'Label'], ['Api-Version' => ApiRevision::Initial->value])
            ->assertOk()
            ->assertExactJson(['label' => 'Label']);
    }

    private function validate(RevisedTraitFormRequest $request): RevisedTraitFormRequest
    {
        $app = $this->app;
        self::assertNotNull($app);

        $request->setContainer($app)->setRedirector($app->make(Redirector::class));
        $request->validateResolved();

        return $request;
    }
}
