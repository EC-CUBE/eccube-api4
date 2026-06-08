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
use Plugin\Api44\Entity\WebHook;
use Plugin\Api44\Form\Type\Admin\WebHookType;
use Plugin\Api44\Repository\WebHookRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WebHookController extends AbstractController
{
    /**
     * @var WebHookRepository
     */
    private WebHookRepository $webHookRepository;

    /**
     * WebHookController constructor.
     *
     * @param WebHookRepository $webHookRepository
     */
    public function __construct(WebHookRepository $webHookRepository)
    {
        $this->webHookRepository = $webHookRepository;
    }

    #[Route(path: '/%eccube_admin_route%/api/webhook', name: 'admin_api_webhook', methods: ['GET'])]
    public function index(): Response
    {
        $WebHooks = $this->webHookRepository->findAll();

        return $this->render('@Api44/admin/WebHook/index.twig', [
            'webhooks' => $WebHooks,
        ]);
    }

    /**
     * @param Request $request
     * @param WebHook|null $WebHook
     *
     * @return Response|RedirectResponse
     */
    #[Route(path: '/%eccube_admin_route%/api/webhook/new', name: 'admin_api_webhook_new', methods: ['GET', 'POST'])]
    #[Route(path: '/%eccube_admin_route%/api/webhook/edit/{id}', requirements: ['id' => '\d+'], name: 'admin_api_webhook_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ?WebHook $WebHook = null): RedirectResponse|Response
    {
        $WebHook = $WebHook ?: new WebHook();
        $builder = $this->formFactory->createBuilder(WebHookType::class, $WebHook);
        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->webHookRepository->save($WebHook);
            $this->entityManager->flush();

            $this->addSuccess('admin.common.save_complete', 'admin');

            return $this->redirectToRoute('admin_api_webhook_edit', ['id' => $WebHook->getId()]);
        }

        return $this->render('@Api44/admin/WebHook/edit.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param WebHook $WebHook
     *
     * @return RedirectResponse
     */
    #[Route(path: '/%eccube_admin_route%/api/webhook/delete/{id}', requirements: ['id' => '\d+'], name: 'admin_api_webhook_delete', methods: ['DELETE'])]
    public function delete(WebHook $WebHook): RedirectResponse
    {
        $this->isTokenValid();

        try {
            $this->webHookRepository->delete($WebHook);
            $this->entityManager->flush();
            $this->addSuccess('admin.common.delete_complete', 'admin');
        } catch (\Exception $e) {
            $this->addError('admin.common.delete_error', 'admin');
            log_error('WebHook削除エラー', [$WebHook->getId(), $e]);
        }

        return $this->redirectToRoute('admin_api_webhook');
    }
}
