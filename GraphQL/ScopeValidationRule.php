<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\Api44\GraphQL;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Validator\QueryValidationContext;
use GraphQL\Validator\Rules\ValidationRule;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ScopeValidationRule extends ValidationRule
{
    /**
     * @var AuthorizationCheckerInterface
     */
    private AuthorizationCheckerInterface $authorizationChecker;

    /**
     * ScopeValidationRule constructor.
     */
    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * @return array<string, callable(Node): void>
     */
    public function getVisitor(QueryValidationContext $context): array
    {
        return [
            NodeKind::OPERATION_DEFINITION => function (Node $def) use ($context): void {
                if (!$def instanceof OperationDefinitionNode) {
                    return;
                }
                if ($def->operation === 'query' && !$this->authorizationChecker->isGranted('ROLE_OAUTH2_READ')) {
                    $context->reportError(new Error('Insufficient permission. (read)'));
                } elseif ($def->operation === 'mutation'
                          && !($this->authorizationChecker->isGranted('ROLE_OAUTH2_READ') && $this->authorizationChecker->isGranted('ROLE_OAUTH2_WRITE'))) {
                    $context->reportError(new Error('Insufficient permission. (read,write)'));
                }
            },
        ];
    }
}
