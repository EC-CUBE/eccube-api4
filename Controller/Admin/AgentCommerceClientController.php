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

namespace Plugin\Api44\Controller\Admin;

use Eccube\Controller\AbstractController;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\OAuth2Grants;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Plugin\Api44\Form\Type\Admin\AgentCommerceClientType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * エージェントコマース (ACP/UCP) 用 OAuth2 クライアントの登録画面 (#188)。
 *
 * protocol ごとに入口を分ける理由は {@link AgentCommerceClientType} を参照。
 * grant は client_credentials 固定、 scope は protocol のものだけを付与する。
 */
class AgentCommerceClientController extends AbstractController
{
    public function __construct(
        private readonly ClientManagerInterface $clientManager,
    ) {
    }

    #[Route(path: '/%eccube_admin_route%/api/oauth/acp/new', name: 'admin_api_oauth_acp_new', methods: ['GET', 'POST'])]
    public function createAcp(Request $request): RedirectResponse|Response
    {
        return $this->createClient($request, 'acp');
    }

    #[Route(path: '/%eccube_admin_route%/api/oauth/ucp/new', name: 'admin_api_oauth_ucp_new', methods: ['GET', 'POST'])]
    public function createUcp(Request $request): RedirectResponse|Response
    {
        return $this->createClient($request, 'ucp');
    }

    private function createClient(Request $request, string $protocol): RedirectResponse|Response
    {
        $form = $this->createForm(AgentCommerceClientType::class, null, ['protocol' => $protocol]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $client = new Client(
                    (string) $form->get('name')->getData(),
                    (string) $form->get('identifier')->getData(),
                    (string) $form->get('secret')->getData()
                );
                $client->setActive(true);
                // エージェントは会員でもブラウザでもなく同意画面を経由できないため、 M2M の
                // client_credentials に固定する (authorization_code / refresh_token は付与しない)。
                $client->setGrants(new Grant(OAuth2Grants::CLIENT_CREDENTIALS));
                $client->setScopes(...array_map(
                    static fn (string $scope): Scope => new Scope($scope),
                    $form->get('scopes')->getData()
                ));

                $this->clientManager->save($client);

                $this->addSuccess('admin.common.save_complete', 'admin');

                // league はシークレットを保存時にハッシュ化せず、 初回のトークン取得成功時に
                // bcrypt へ日和見アップグレードする (ClientRepository::validateClient)。
                // つまり一度使われた後の一覧表示はハッシュ値で、 事業者へ渡す値を復元できない。
                // 発行直後のこの画面でだけ平文を提示する (redirect すると失われるため render する)。
                $response = $this->render('@Api44/admin/OAuth/agent_commerce_client_issued.twig', [
                    'name' => (string) $form->get('name')->getData(),
                    'identifier' => (string) $form->get('identifier')->getData(),
                    'secret' => (string) $form->get('secret')->getData(),
                ]);
                // 認証情報を HTML に埋め込む画面なので、 ブラウザや共有端末のキャッシュに残さない
                $response->headers->set('Cache-Control', 'no-store, private');

                return $response;
            } catch (\Exception $e) {
                $this->addError(trans('admin.common.save_error'), 'admin');
                log_error('エージェントコマース OAuth2 Client 登録エラー', ['exception' => $e, 'protocol' => $protocol]);
            }
        }

        return $this->render('@Api44/admin/OAuth/agent_commerce_client.twig', [
            'form' => $form->createView(),
            'protocol' => $protocol,
        ]);
    }
}
