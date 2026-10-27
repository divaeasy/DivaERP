<?php

namespace App\EventSubscriber;

use App\Entity\Dossier;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class CurrentDossierRefreshSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $em
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $user = $this->security->getUser();
        if (!$user || !method_exists($user, 'getCurrentDossier')) {
            return;
        }

        $current = $user->getCurrentDossier();
        if (!$current instanceof Dossier || !$current->getId()) {
            return;
        }

        $fresh = $this->em->getRepository(Dossier::class)->find($current->getId());
        if ($fresh) {
            // Replace the in-memory reference so Twig gets up-to-date theme/logo.
            $user->setCurrentDossier($fresh);
        }
    }
}
