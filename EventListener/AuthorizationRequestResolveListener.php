<?php


namespace Plugin\Api\EventListener;

use Eccube\Entity\Member;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Trikoder\Bundle\OAuth2Bundle\Event\AuthorizationRequestResolveEvent;
use Trikoder\Bundle\OAuth2Bundle\OAuth2Events;

final class AuthorizationRequestResolveListener implements EventSubscriberInterface
{
    /**
     * 承認する authority_id
     * @var int
     */
    const APPROVE_AUTHORITY_ID = 0; //  システム管理者

    public static function getSubscribedEvents(): array
    {
        return [
            OAuth2Events::AUTHORIZATION_REQUEST_RESOLVE => 'onAuthorizationRequestResolve',
        ];
    }

    public function onAuthorizationRequestResolve(AuthorizationRequestResolveEvent $event): void
    {
        $user = $event->getUser();

        if ($user instanceof Member && $user->getAuthority()->getId() === self::APPROVE_AUTHORITY_ID) {
            $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_APPROVED);
        } else {
            $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_DENIED);
        }
    }
}
