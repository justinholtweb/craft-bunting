<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JustinHoltWeb\Bunting\Bunting;
use Symfony\Component\HttpFoundation\Response;

/**
 * `->middleware('bunting:new-nav')` (or `bunting:a,b` — all must be on) on app
 * routes: a 404 while the flag is off for the request. Same rule as
 * `{% requireFeature %}` and `@requireFeature`.
 */
final readonly class RequireFeature
{
    public function __construct(private Bunting $bunting) {}

    public function handle(Request $request, Closure $next, string ...$handles): Response
    {
        // No flag named fails closed rather than letting the route through.
        if ($handles === []) {
            abort(404);
        }

        $this->bunting->requireFeature(...$handles);

        return $next($request);
    }
}
