<?php

declare(strict_types=1);

namespace Test\Feature\Laravel\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A middleware of the application aliased `jsonapi` before the package boots.
 */
final class ApplicationMiddleware
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
