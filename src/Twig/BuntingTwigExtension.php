<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Twig;

use Laravel\Pennant\Feature;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig mirrors of Blade's Pennant directives:
 *
 * - `{% if feature('new-nav') %}` ≙ `@feature('new-nav')` (Pennant's default store —
 *   Bunting's, unless the “Make Bunting Pennant's default store” setting is off)
 * - `{% if featureAny(['a', 'b']) %}` ≙ `@featureany(['a', 'b'])`
 * - `{% requireFeature 'new-nav' %}` ≙ `@requireFeature('new-nav')` (404 while off)
 */
class BuntingTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('feature', fn (string $feature): bool => Feature::active($feature)),
            new TwigFunction('featureAny', fn (array $features): bool => Feature::someAreActive(array_values(array_map('strval', $features)))),
        ];
    }

    public function getTokenParsers(): array
    {
        return [new RequireFeatureTokenParser];
    }
}
