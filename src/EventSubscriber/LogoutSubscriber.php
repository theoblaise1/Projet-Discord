<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Affiche un message de confirmation après la déconnexion.
 */
final class LogoutSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Priorité négative : exécuté APRÈS l'invalidation de la session par Symfony,
        // sinon le message flash serait effacé avec le reste de la session.
        return [LogoutEvent::class => ['onLogout', -64]];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', 'Vous êtes bien déconnecté. À bientôt !');
        }
    }
}
