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

namespace Plugin\Api\Controller;

use Eccube\Controller\AbstractController;
use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Validator\DocumentValidator;
use Plugin\Api\GraphQL\Schema;
use Plugin\Api\GraphQL\ScopeValidationRule;
use Plugin\Api\GraphQL\Types;
use RuntimeException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends AbstractController
{
    /**
     * @var Types
     */
    private $types;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var ScopeValidationRule
     */
    private $scopeValidationRule;

    public function __construct(
        Types $types,
        KernelInterface $kernel,
        Schema $schema,
        ScopeValidationRule $scopeValidationRule
    ) {
        $this->types = $types;
        $this->kernel = $kernel;
        $this->schema = $schema;
        $this->scopeValidationRule = $scopeValidationRule;
    }

    /**
     * @Route("/api", name="api", methods={"GET", "POST"})
     * @Security("has_role('ROLE_OAUTH2_READ') or has_role('ROLE_OAUTH2_WRITE')")
     */
    public function index(Request $request)
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
                throw new RuntimeException();
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
