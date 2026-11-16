<?php

declare(strict_types=1);

namespace Test\Unit\Http;

use Ark4ne\ApiRevisions\Schema\ListSchema;
use Ark4ne\ApiRevisions\Schema\ObjectSchema;
use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Http\Body;
use Ark4ne\JsonApi\Http\Rules\Linkage;
use Test\app\Http\Resources\UserResource;
use Test\TestCase;
use Test\Unit\Http\Fixtures\OrphanFormRequest;

final class BodyTest extends TestCase
{
    public function test_the_document_holds_the_type_of_the_resource_only(): void
    {
        $data = self::data(Body::resource(UserResource::class));

        self::assertTrue($data->isRequired());
        self::assertSame(['type'], array_keys($data->children()));
        self::assertTrue($data->children()['type']->isRequired());
        self::assertSame(['in:user'], $data->children()['type']->declaredRules());
    }

    public function test_the_attributes_and_the_relationships_are_required_when_one_of_their_members_is(): void
    {
        $optional = self::data(Body::resource(
            UserResource::class,
            attributes: ['name' => Schema::string()],
            relationships: ['posts' => Body::toMany('posts')],
        ))->children();
        $required = self::data(Body::resource(
            UserResource::class,
            attributes: ['name' => Schema::string()->required()],
            relationships: ['posts' => Body::toMany('posts')->required()],
            id: Schema::string(),
        ))->children();

        self::assertSame(['type', 'attributes', 'relationships'], array_keys($optional));
        self::assertFalse($optional['attributes']->isRequired());
        self::assertFalse($optional['relationships']->isRequired());
        self::assertSame(['type', 'id', 'attributes', 'relationships'], array_keys($required));
        self::assertTrue($required['attributes']->isRequired());
        self::assertTrue($required['relationships']->isRequired());
    }

    public function test_a_to_one_linkage_is_an_identifier_or_null(): void
    {
        $data = Body::toOne(UserResource::class, 'admins')->children()['data'];

        self::assertInstanceOf(ObjectSchema::class, $data);
        self::assertTrue($data->isNullable());
        self::assertEquals([new Linkage()], $data->declaredRules());
        self::assertSame(['in:user,admins'], $data->children()['type']->declaredRules());
        self::assertTrue($data->children()['id']->isRequired());
    }

    public function test_a_linkage_without_related_resource_accepts_any_type(): void
    {
        $data = Body::toOne()->children()['data'];

        self::assertInstanceOf(ObjectSchema::class, $data);
        self::assertTrue($data->children()['type']->isRequired());
        self::assertSame([], $data->children()['type']->declaredRules());
    }

    public function test_a_to_many_linkage_is_a_required_list_of_identifiers(): void
    {
        $data = Body::toMany('tags')->children()['data'];

        self::assertTrue($data->isRequired());
        self::assertFalse($data->isNullable());
        self::assertInstanceOf(ListSchema::class, $data);
    }

    public function test_a_form_request_without_resource_cannot_declare_its_resource_object(): void
    {
        $this->expectException(DefinitionException::class);

        (new OrphanFormRequest())->declared();
    }

    /**
     * @param array<string, \Ark4ne\ApiRevisions\Schema\Descriptor<mixed>> $body
     */
    private static function data(array $body): ObjectSchema
    {
        self::assertSame(['data'], array_keys($body));
        self::assertInstanceOf(ObjectSchema::class, $body['data']);

        return $body['data'];
    }
}
