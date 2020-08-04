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

namespace Plugin\Api\Controller\Admin;

use Eccube\Controller\AbstractController;
use Exception;
use Plugin\Api\Form\Type\Admin\ClientType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Trikoder\Bundle\OAuth2Bundle\Manager\AccessTokenManagerInterface;
use Trikoder\Bundle\OAuth2Bundle\Manager\ClientFilter;
use Trikoder\Bundle\OAuth2Bundle\Manager\ClientManagerInterface;
use Trikoder\Bundle\OAuth2Bundle\Manager\RefreshTokenManagerInterface;
use Trikoder\Bundle\OAuth2Bundle\Model\AuthorizationCode;
use Trikoder\Bundle\OAuth2Bundle\Model\Client;
use Trikoder\Bundle\OAuth2Bundle\Model\Grant;
use Trikoder\Bundle\OAuth2Bundle\Model\RedirectUri;
use Trikoder\Bundle\OAuth2Bundle\Model\Scope;
use Trikoder\Bundle\OAuth2Bundle\OAuth2Grants;

class OAuthController extends AbstractController
{
    /**
     * @var ClientManagerInterface
     */
    private $clientManager;
    /**
     * @var AccessTokenManagerInterface
     */
    private $accessTokenManager;
    /**
     * @var RefreshTokenManagerInterface
     */
    private $refreshTokenManager;

    /**
     * OAuthController constructor.
     *
     * @param ClientManagerInterface $clientManager
     * @param AccessTokenManagerInterface $accessTokenManager
     * @param RefreshTokenManagerInterface $refreshTokenManager
     */
    public function __construct(
        ClientManagerInterface $clientManager,
        AccessTokenManagerInterface $accessTokenManager,
        RefreshTokenManagerInterface $refreshTokenManager
    ) {
        $this->clientManager = $clientManager;
        $this->accessTokenManager = $accessTokenManager;
        $this->refreshTokenManager = $refreshTokenManager;
    }

    /**
     * @Route("/%eccube_admin_route%/api/config", name="admin_api_config", methods={"GET"})
     * @Route("/%eccube_admin_route%/api/oauth", name="admin_api_oauth", methods={"GET"})
     * @Template("@Api/admin/OAuth/index.twig")
     *
     * @param Request $request
     *
     * @return array
     */
    public function index(Request $request)
    {
        $criteria = ClientFilter::create();
        $clients = $this->clientManager->list($criteria);

        return [
            'clients' => $clients,
        ];
    }

    /**
     * @Route("/%eccube_admin_route%/api/oauth/new", name="admin_api_oauth_new", methods={"GET", "POST"})
     * @Template("@Api/admin/OAuth/edit.twig")
     *
     * @param Request $request
     *
     * @return array|RedirectResponse
     *
     * @throws Exception
     */
    public function create(Request $request)
    {
        $builder = $this->formFactory
            ->createBuilder(ClientType::class);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $identifier = $form->get('identifier')->getData();
            $secret = $form->get('secret')->getData();

            try {
                $client = new Client($identifier, $secret);
                $client = $this->updateClientFromForm($client, $form);

                $this->clientManager->save($client);

                $this->addSuccess('admin.common.save_complete', 'admin');

                return $this->redirectToRoute('admin_api_oauth');
            } catch (Exception $e) {
                $this->addError(trans('admin.common.save_error'), 'admin');
                log_error('OAuth2 Client 登録エラー', [$e->getMessage()]);
            }
        }

        return [
            'form' => $form->createView(),
        ];
    }

    /**
     * @Route(
     *     "/%eccube_admin_route%/api/oauth/delete/{identifier}",
     *     requirements={"identifier" = "\w+"},
     *     name="admin_api_oauth_delete",
     *     methods={"DELETE"}
     * )
     *
     * @param Request $request
     * @param string $identifier
     *
     * @return RedirectResponse
     */
    public function delete(Request $request, string $identifier)
    {
        $this->isTokenValid();

        $client = $this->clientManager->find($identifier);
        if (null === $client) {
            $this->addError('admin.common.delete_error_already_deleted', 'admin');

            return $this->redirectToRoute('admin_api_oauth');
        }

        try {
            $this->deleteAuthorizationCode($client);
            $this->clientManager->remove($client);

            $this->addSuccess('admin.common.delete_complete', 'admin');
        } catch (Exception $e) {
            $this->addError('admin.common.delete_error', 'admin');

            log_error('OAuth2 Client 削除エラー', [$e->getMessage()]);
        }

        return $this->redirectToRoute('admin_api_oauth');
    }

    /**
     * @Route(
     *     "/%eccube_admin_route%/api/oauth/clear_expired_tokens",
     *     name="admin_api_oauth_clear_expired_tokens",
     *     methods={"DELETE"}
     * )
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function clearExpiredTokens(Request $request)
    {
        try {
            $this->accessTokenManager->clearExpired();
            $this->refreshTokenManager->clearExpired();

            $this->addSuccess('admin.common.delete_complete', 'admin');
        } catch (Exception $e) {
            $this->addError(trans('admin.common.delete_error'), 'admin');
            log_error('OAuth2 Token 削除エラー', [$e->getMessage()]);
        }

        return $this->redirectToRoute('admin_api_oauth');
    }

    /**
     * @param Client $client
     * @param FormInterface $form
     *
     * @return Client
     */
    private function updateClientFromForm(Client $client, FormInterface $form): Client
    {
        $client->setActive(true);

        $redirectUris = array_map(
            function (string $redirectUri): RedirectUri {
                return new RedirectUri($redirectUri);
            },
            explode(',', $form->get('redirect_uris')->getData())
        );
        $client->setRedirectUris(...$redirectUris);

        $grants = array_map(
            function (string $grant): Grant {
                return new Grant($grant);
            },
            $form->get('grants')->getData()
        );
        // authorization code grant が選択されていた場合には refresh token grant も付与
        if (in_array(OAuth2Grants::AUTHORIZATION_CODE, $grants)) {
            array_push($grants, new Grant(OAuth2Grants::REFRESH_TOKEN));
        }
        $client->setGrants(...$grants);

        $scopes = array_map(
            function (string $scope): Scope {
                return new Scope($scope);
            },
            $form->get('scopes')->getData()
        );
        $client->setScopes(...$scopes);

        return $client;
    }

    /**
     * AuthorizationCode が保存されている場合は削除
     *
     * @param Client $client
     * @return int
     */
    private function deleteAuthorizationCode(Client $client)
    {
        return $this->entityManager->createQueryBuilder()
            ->delete(AuthorizationCode::class, 'ac')
            ->where('ac.client = :client')
            ->setParameter('client', $client)
            ->getQuery()
            ->execute();
    }
}
