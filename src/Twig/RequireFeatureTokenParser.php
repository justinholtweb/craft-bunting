<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Twig;

use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * {% requireFeature 'new-nav' %}
 * {% requireFeature ['new-nav', 'new-footer'] %}  (all must be on)
 */
class RequireFeatureTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): Node
    {
        $stream = $this->parser->getStream();
        $handles = $this->parser->parseExpression();
        $stream->expect(Token::BLOCK_END_TYPE);

        return new RequireFeatureNode($handles, $token->getLine());
    }

    public function getTag(): string
    {
        return 'requireFeature';
    }
}
