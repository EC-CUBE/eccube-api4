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

namespace Plugin\Api42\Controller\Admin;

use Eccube\Controller\AbstractController;
use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use Plugin\Api42\GraphQL\Schema;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use RuntimeException;

class GraphiQLController extends AbstractController
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var Schema
     */
    private $schema;

    public function __construct(
        KernelInterface $kernel,
        Schema $schema
    ) {
        $this->kernel = $kernel;
        $this->schema = $schema;
    }

    /**
     * @Route("/%eccube_admin_route%/graphiql", name="admin_api_graphiql", methods={"GET"})
     * @Template("@Api42/admin/OAuth/graphiql.twig")
     *
     * @return array
     */
    public function graphiql()
    {
        if ('dev' !== env('APP_ENV')) {
            throw new AccessDeniedHttpException();
        }
        return [];
    }

    /**
     * @Route("/%eccube_admin_route%/graphiql/api", name="admin_api_graphiql_api", methods={"POST"})
     */
    public function index(Request $request)
    {
        if ('dev' !== env('APP_ENV')) {
            throw new AccessDeniedHttpException();
        }
        $this->isTokenValid();

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

        $result = GraphQL::executeQuery($this->schema, $query, null, null, $variableValues);

        if ($this->kernel->isDebug()) {
            $debug = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;
            $result = $result->toArray($debug);
        }

        return $this->json($result);
    }
}
