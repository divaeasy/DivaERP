<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuse la connexion (formulaire et "se souvenir de moi") aux comptes non verifies.
 * Un compte est verifie par le lien e-mail ou par un admin (case "verifie" de la fiche utilisateur).
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException(
                "Votre compte n'est pas encore activé. Contactez un administrateur."
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
