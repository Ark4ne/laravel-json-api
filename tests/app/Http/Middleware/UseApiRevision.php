<?php

declare(strict_types=1);

namespace Test\app\Http\Middleware;

use Ark4ne\JsonApi\Laravel\JsonApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Test\app\Revisions\ApiRevision;

/**
 * What an application writes: its own header, its own registry (here the cases of its enum), the head without header.
 */
final class UseApiRevision
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Api-Version');

        JsonApi::useRevision($request, is_string($header) ? ApiRevision::tryFrom($header) : null);

        return $next($request);
    }
}
