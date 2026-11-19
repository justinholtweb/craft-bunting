<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Http\Middleware;

use Closure;
use CraftCms\Cms\Site\Sites;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use JustinHoltWeb\Bunting\Bunting;
use JustinHoltWeb\Bunting\Flags\Flags;
use JustinHoltWeb\Bunting\Pennant\ScopeFactory;
use JustinHoltWeb\Bunting\Preview;
use JustinHoltWeb\Bunting\Settings;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on front-end (`craft.web`) requests, before Craft renders a template:
 *
 * 1. `?bunting-preview=` for people allowed to preview;
 * 2. the visitor cookie, when guest rollouts are on;
 * 3. a 404 for a URI gated by a flag that's off for this request.
 */
final readonly class GateSiteRequests
{
    public function __construct(
        private Flags $flags,
        private Bunting $bunting,
        private Preview $preview,
        private Settings $settings,
        private Sites $sites,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isSiteRequest() || $request->isActionRequest()) {
            return $next($request);
        }

        $this->preview->capture($request);
        $this->ensureVisitorCookie($request);

        $uri = $this->sites->getRequestPath($request);

        foreach ($this->flags->all() as $flag) {
            if ($flag->uriPatterns !== [] && $flag->gatesUri($uri) && ! $this->bunting->active($flag->handle)) {
                abort(404);
            }
        }

        $response = $next($request);

        // A page someone previews or a gated page differs per person: keep it out of shared caches.
        if (($this->preview->values() !== [] || $this->gated($uri))) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }

    private function gated(string $uri): bool
    {
        return array_any($this->flags->all(), fn ($flag) => $flag->uriPatterns !== [] && $flag->gatesUri($uri));
    }

    private function ensureVisitorCookie(Request $request): void
    {
        if (! $this->settings->guestRollout || $request->user() !== null) {
            return;
        }

        $id = $request->cookies->get(ScopeFactory::VISITOR_COOKIE);

        if (is_string($id) && preg_match('/^[a-f0-9]{32}$/D', $id)) {
            return;
        }

        $id = bin2hex(random_bytes(16));
        $request->cookies->set(ScopeFactory::VISITOR_COOKIE, $id);
        Cookie::queue(ScopeFactory::VISITOR_COOKIE, $id, 60 * 24 * 365, httpOnly: true, sameSite: 'lax');
    }
}
