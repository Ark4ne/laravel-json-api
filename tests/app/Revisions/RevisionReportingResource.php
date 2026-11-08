<?php

declare(strict_types=1);

namespace Test\app\Revisions;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Models\User;

/**
 * Renders the revision of its rendering, to observe where it comes from.
 *
 * @extends JsonApiResource<User>
 */
#[JsonApiType('reports')]
final class RevisionReportingResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'revision' => $this->string(fn() => $this->renderingContext?->revision->name ?? 'head'),
        ];
    }
}
