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
use Eccube\Entity\Member;
use Plugin\Api44\Form\Type\Admin\McpTokenType;
use Plugin\Api44\Repository\McpTokenRepository;
use Plugin\Api44\Service\McpTokenService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

class McpTokenController extends AbstractController
{
    public function __construct(
        private readonly McpTokenService $mcpTokenService,
        private readonly McpTokenRepository $mcpTokenRepository,
    ) {
    }

    /**
     * MCP トークン発行画面。 送信時にトークンを発行し、 JWT を 1 度だけ表示する。
     */
    #[Route(path: '/%eccube_admin_route%/api/oauth/mcp/new', name: 'admin_api_mcp_token_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $form = $this->createForm(McpTokenType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $member = $this->getUser();
            if (!$member instanceof Member) {
                throw new AccessDeniedHttpException();
            }

            try {
                // sub はフォーム入力でなく操作中の Member 固定 (なりすまし防止)
                $token = $this->mcpTokenService->issue(
                    $member,
                    (string) $form->get('label')->getData(),
                    $form->get('scopes')->getData(),
                    (int) $form->get('expire')->getData(),
                );

                // 発行直後のみ JWT を表示する (再表示不可)。 redirect すると失われるため render する
                $response = $this->render('@Api44/admin/OAuth/mcp_token_issued.twig', [
                    'token' => $token,
                    'label' => (string) $form->get('label')->getData(),
                ]);
                // bearer token を HTML に埋め込む画面なので、 ブラウザや共有端末のキャッシュに残さない
                $response->headers->set('Cache-Control', 'no-store, private');

                return $response;
            } catch (\Exception $e) {
                $this->addError(trans('admin.common.save_error'), 'admin');
                // 例外クラスと発行者を残し、 万一の発行失敗を追跡可能にする
                log_error('MCP トークン発行エラー', ['exception' => $e, 'member_id' => $member->getId()]);
            }
        }

        return $this->render('@Api44/admin/OAuth/mcp_token.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * MCP トークンを失効する (league token を revoke → 即 401)。
     */
    #[Route(path: '/%eccube_admin_route%/api/oauth/mcp/revoke/{id}', requirements: ['id' => '\d+'], name: 'admin_api_mcp_token_revoke', methods: ['DELETE'])]
    public function revoke(Request $request, int $id): RedirectResponse
    {
        $this->isTokenValid();

        $mcpToken = $this->mcpTokenRepository->find($id);
        if (null === $mcpToken) {
            $this->addError('admin.common.delete_error_already_deleted', 'admin');

            return $this->redirectToRoute('admin_api_oauth');
        }

        try {
            $found = $this->mcpTokenService->revoke($mcpToken);
            if ($found) {
                $this->addSuccess('admin.common.delete_complete', 'admin');
            } else {
                // 失効対象の league token が見つからなかった (期限切れ削除済み等)。 メタは削除したが操作者に明示する
                $this->addWarning('api.admin.oauth.mcp_token.revoke__not_found', 'admin');
            }
        } catch (\Exception $e) {
            $this->addError('admin.common.delete_error', 'admin');
            log_error('MCP トークン失効エラー', ['exception' => $e, 'id' => $id]);
        }

        return $this->redirectToRoute('admin_api_oauth');
    }
}
