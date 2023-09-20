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
use Eccube\Security\SecurityContext;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use League\Bundle\OAuth2ServerBundle\Manager\AccessTokenManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\RefreshTokenManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\AccessToken;
use League\Bundle\OAuth2ServerBundle\Model\RefreshToken;
use League\Bundle\OAuth2ServerBundle\Repository\AccessTokenRepository;
use League\Bundle\OAuth2ServerBundle\Repository\RefreshTokenRepository;
use Plugin\Api42\GraphQL\Schema;
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
    private SecurityContext $securityContext;
    private RefreshTokenRepository $refreshTokenRepository;
    private AccessTokenRepository $accessTokenRepository;
    private RefreshTokenManagerInterface $refreshTokenManager;
    private AccessTokenManagerInterface $accessTokenManager;

    public function __construct(
        Types $types,
        KernelInterface $kernel,
        Schema $schema,
        SecurityContext $securityContext,
        RefreshTokenRepository $refreshTokenRepository,
        AccessTokenRepository $accessTokenRepository,
        RefreshTokenManagerInterface $refreshTokenManager,
        AccessTokenManagerInterface $accessTokenManager
    ) {
        $this->types = $types;
        $this->kernel = $kernel;
        $this->schema = $schema;
        $this->securityContext = $securityContext;
        $this->refreshTokenRepository = $refreshTokenRepository;

        $this->accessTokenRepository = $accessTokenRepository;
        $this->refreshTokenManager = $refreshTokenManager;
        $this->accessTokenManager = $accessTokenManager;
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

    /**
     * ログアウトしてOAuth2のセッションを無効化する.
     * @Route("/api/logout", name="api_logout", methods={"POST", "OPTIONS"})
     */
    public function logoutAndInvalidateOAuth2Session(): JsonResponse
    {
        $user = $this->securityContext->getLoginUser();
        if ($user !== null) {
            /** @var AccessToken[]|null $tokenList */
            $tokenList = $this->entityManager->getRepository(AccessToken::class)->findBy(['userIdentifier' => $user->getUsername()]);
            foreach ($tokenList as $tokenRow) {
                $refreshTokenList = $this->entityManager->getRepository(RefreshToken::class)->findBy(['accessToken' => $tokenRow->getIdentifier()]);
                foreach ($refreshTokenList as $refreshTokenRow) {
                    // ユーザーのリフレッシュトークンを削除
                    $this->refreshTokenRepository->revokeRefreshToken($refreshTokenRow->getIdentifier());
                    $this->entityManager->flush();
                }

                // ユーザーのアクセストークンを削除
                $this->accessTokenRepository->revokeAccessToken($tokenRow->getIdentifier());
                $this->entityManager->flush();
            }
        } else {
            log_alert('IGNORED, NO ACTIVE USER');
        }

        // 他の有効期限切れたトークンを削除
        $this->accessTokenManager->clearExpired();
        $this->refreshTokenManager->clearExpired();

        $jsonResponse = new JsonResponse();
        $jsonResponse->setContent(json_encode(['message' => 'success']));

        return $jsonResponse;
    }
}
