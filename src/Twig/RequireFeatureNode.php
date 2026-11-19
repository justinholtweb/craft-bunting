<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Twig;

use Illuminate\Support\Facades\App;
use JustinHoltWeb\Bunting\Bunting;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;

#[YieldReady]
class RequireFeatureNode extends Node
{
    public function __construct(AbstractExpression $handles, int $lineno)
    {
        parent::__construct(['handles' => $handles], [], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this)
            ->write('\\'.App::class.'::make(\\'.Bunting::class.'::class)->requireFeature(...array_map(\'strval\', (array) (')
            ->subcompile($this->getNode('handles'))
            ->raw(")));\n");
    }
}
