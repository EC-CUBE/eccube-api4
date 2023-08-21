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

namespace Plugin\Api42\Controller;

use Eccube\Controller\AbstractController;
use Eccube\Http\JsonResponse;
use Eccube\Http\Response;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Validator\DocumentValidator;
use Plugin\Api42\GraphQL\Schema;
use Plugin\Api42\GraphQL\ScopeValidationRule;
use Plugin\Api42\GraphQL\Types;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
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
     * @Route("/api", name="api", methods={"GET", "POST", "OPTIONS"})
     * @IsGranted("ROLE_OAUTH2_READ", "ROLE_OAUTH2_WRITE")
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
            case 'OPTIONS':
                $CORSResponse = new Response();
                $CORSResponse->headers->set('Access-Control-Allow-Origin', '*');
                $CORSResponse->headers->set('Content-Type', 'application/json');
                $CORSResponse->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
                $CORSResponse->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
                return $CORSResponse;
            default:
                throw new \RuntimeException();
        }

        DocumentValidator::addRule($this->scopeValidationRule);

        /** @var Error[] $warnings */
        $warnings = [];
        /** @var Error[] $infos */
        $infos = [];

        $result = GraphQL::executeQuery(
            $this->schema, $query, null,
            [
                'warnings' => &$warnings,
                'infos' => &$infos,
            ],
            $variableValues
        );
        $result->errors = array_merge($result->errors, $warnings, $infos);

        if ($this->kernel->isDebug()) {
            $debug = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;
            $result = $result->toArray($debug);
        }

        $jsonResult = new JsonResponse();
        $jsonResult->setContent(json_encode($result));
        $jsonResult->headers->set('Access-Control-Allow-Origin', '*');
        $jsonResult->headers->set('Content-Type', 'application/json');
        $jsonResult->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');

        return $jsonResult;
    }
}
