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

namespace Plugin\Api44\Controller;

use Eccube\Controller\AbstractController;
use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Validator\DocumentValidator;
use Plugin\Api44\GraphQL\Schema;
use Plugin\Api44\GraphQL\ScopeValidationRule;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ApiController extends AbstractController
{
    /**
     * @var KernelInterface
     */
    private KernelInterface $kernel;

    /**
     * @var Schema
     */
    private Schema $schema;

    /**
     * @var ScopeValidationRule
     */
    private ScopeValidationRule $scopeValidationRule;

    public function __construct(
        KernelInterface $kernel,
        Schema $schema,
        ScopeValidationRule $scopeValidationRule,
    ) {
        $this->kernel = $kernel;
        $this->schema = $schema;
        $this->scopeValidationRule = $scopeValidationRule;
    }

    #[Route(path: '/api', name: 'api', methods: ['GET', 'POST'])]
    #[IsGranted(new Expression("is_granted('ROLE_OAUTH2_READ') or is_granted('ROLE_OAUTH2_WRITE')"))]
    public function index(Request $request): JsonResponse
    {
        switch ($request->getMethod()) {
            case 'GET':
                $query = $request->get('query');
                $variableValues = json_decode($request->get('variables'), true);
                break;
            case 'POST':
                $body = json_decode($request->getContent(), true);
                $query = $body['query'];
                $variableValues = isset($body['variables']) ? $body['variables'] : null;
                break;
            default:
                throw new \RuntimeException();
        }

        DocumentValidator::addRule($this->scopeValidationRule);
        $result = GraphQL::executeQuery($this->schema, $query, null, null, $variableValues);

        if ($this->kernel->isDebug()) {
            $debug = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;
            $result = $result->toArray($debug);
        }

        return $this->json($result);
    }
}
