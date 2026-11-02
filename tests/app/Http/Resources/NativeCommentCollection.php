<?php

declare(strict_types=1);

namespace Test\app\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A collection of Laravel's native comments.
 */
final class NativeCommentCollection extends ResourceCollection
{
    public $collects = NativeCommentResource::class;
}
