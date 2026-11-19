<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Listeners;

use CraftCms\Cms\View\Events\PageTemplateRendering;
use CraftCms\Cms\View\TemplateMode;
use JustinHoltWeb\Bunting\Bunting;
use JustinHoltWeb\Bunting\Flags\Flags;
use JustinHoltWeb\Bunting\Http\Middleware\GateSiteRequests;
use Throwable;

/**
 * The second half of URI gating. {@see GateSiteRequests}
 * checks the request path; this checks the template Craft actually resolved for a
 * template-routed page, so no spelling of the URL (`/index`, an extension, case,
 * encoded slashes) can reach a gated template.
 */
class GateTemplates
{
    public function __construct(private readonly Flags $flags, private readonly Bunting $bunting) {}

    public function handle(PageTemplateRendering $event): void
    {
        if ($event->templateMode !== TemplateMode::Site) {
            return;
        }

        try {
            $request = request();

            // Element pages are matched on their URI by the middleware; their template
            // (e.g. `articles/_entry`) isn't a URL and isn't gated by URI patterns.
            if (! $request->isSiteRequest() || $request->isActionRequest() || ! $request->routeIs('craft.siteFallback')) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        foreach ($this->flags->all() as $flag) {
            if ($flag->uriPatterns !== [] && $flag->gatesUri($event->template) && ! $this->bunting->active($flag->handle)) {
                abort(404);
            }
        }
    }
}
