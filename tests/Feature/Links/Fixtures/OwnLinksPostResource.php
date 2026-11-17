<?php

declare(strict_types=1);

namespace Test\Feature\Links\Fixtures;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiRoutes;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
#[JsonApiRoutes(self: 'posts.show')]
final class OwnLinksPostResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['title' => Schema::string()->nullable()->whenHas()];
    }

    /**
     * @return iterable<string, string>
     */
    protected function toLinks(Request $request): iterable
    {
        return ['self' => 'https://own.example.com'];
    }
}
