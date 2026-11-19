<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Listeners;

use CraftCms\Cms\Twig\Events\TwigCreated;
use CraftCms\Cms\View\Events\TemplateGlobalsResolving;
use CraftCms\Cms\View\TemplateMode;
use JustinHoltWeb\Bunting\Bunting;
use JustinHoltWeb\Bunting\Twig\BuntingTwigExtension;

/**
 * `bunting` as a template global (Craft feeds these to Twig *and* Blade), plus
 * `feature()` / `featureAny()` / `{% requireFeature %}` for Twig. Blade gets
 * Pennant's own `@feature` / `@featureany`, and `@requireFeature` from the plugin.
 */
class RegisterTemplateApi
{
    public function globals(TemplateGlobalsResolving $event): void
    {
        $event->globals['bunting'] = app(Bunting::class);
    }

    public function twig(TwigCreated $event): void
    {
        if ($event->templateMode === TemplateMode::Site) {
            $event->twig->addExtension(new BuntingTwigExtension);
        }
    }

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            TemplateGlobalsResolving::class => 'globals',
            TwigCreated::class => 'twig',
        ];
    }
}
