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

namespace Plugin\Api42\EventListener;

use Eccube\Entity\Master\Authority;
use Eccube\Entity\Member;
use League\OAuth2\Server\Exception\OAuthServerException;
use Plugin\Api42\Form\Type\Admin\OAuth2AuthorizationType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;
use League\Bundle\OAuth2ServerBundle\OAuth2Events;
use Twig\Environment as Twig;

final class AuthorizationRequestResolveListener implements EventSubscriberInterface
{
    /** @var Twig */
    protected $twig;

    /** @var FormFactoryInterface */
    protected $formFactory;

    /** @var RequestStack */
    protected $requestStack;

    public function __construct(
        Twig $twig,
        FormFactoryInterface $formFactory,
        RequestStack $requestStack
    ) {
        $this->twig = $twig;
        $this->formFactory = $formFactory;
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OAuth2Events::AUTHORIZATION_REQUEST_RESOLVE => 'onAuthorizationRequestResolve',
        ];
    }

    public function onAuthorizationRequestResolve(AuthorizationRequestResolveEvent $event): void
    {
        $user = $event->getUser();
        $request = $this->requestStack->getMainRequest();

        // システム管理者以外は承認しない
        if (!$user instanceof Member || $user->getAuthority()->getId() !== Authority::ADMIN) {
            $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_DENIED);

            return;
        }

        if (!$request->query->has('redirect_uri')) {
            // redirect_uri_mismatch を返すべきだが OAuthServerException ではサポートされていない
            // http://openid-foundation-japan.github.io/draft-ietf-oauth-v2.ja.html#auth-error-codes
            throw OAuthServerException::invalidRequest('redirect_uri');
        }

        if (!$event->isAuthorizationApproved()) {
            $builder = $this->formFactory->createBuilder(OAuth2AuthorizationType::class);
            $form = $builder->getForm();

            $form['client_id']->setData($event->getClient()->getIdentifier());
            $form['client_secret']->setData($event->getClient()->getSecret());
            $form['redirect_uri']->setData($event->getRedirectUri());
            $form['state']->setData($event->getState());
            $form['scope']->setData(join(' ', $event->getScopes()));
            $content = $this->twig->render(
                '@Api42/admin/OAuth/authorization.twig',
                [
                    'scopes' => $event->getScopes(),
                    'form' => $form->createView(),
                ]
            );

            if ('POST' === $request->getMethod()) {
                $form->handleRequest($request);
                if ($form->isSubmitted() && $form->isValid()) {
                    if ($form->get('approve')->isClicked()) {
                        $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_APPROVED);
                    }
                } else {
                    $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_DENIED);
                }
            } else {
                $event->setResponse(Response::create($content));
            }
        }
    }
}
