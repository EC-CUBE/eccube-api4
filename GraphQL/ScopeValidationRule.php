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

namespace Plugin\Api\GraphQL;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\ValidationContext;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ScopeValidationRule extends ValidationRule
{
    /**
     * @var AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    /**
     * ScopeValidationRule constructor.
     */
    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::OPERATION_DEFINITION => function (OperationDefinitionNode $def) use ($context) {
                if ($def->operation === 'query' && !$this->authorizationChecker->isGranted('ROLE_OAUTH2_READ')) {
                    $context->reportError(new Error('Insufficient permission. (read)'));
                } elseif ($def->operation === 'mutation' && !$this->authorizationChecker->isGranted(['ROLE_OAUTH2_READ', 'ROLE_OAUTH2_WRITE'])) {
                    $context->reportError(new Error('Insufficient permission. (read,write)'));
                }
            },
        ];
    }
}
