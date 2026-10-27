<?php

namespace App\Security\Voter;

use App\Entity\CodeOperation;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CodeOperationVoter extends Voter
{
    public const CREATE = 'CODE_OPERATION_CREATE';
    public const EDIT = 'CODE_OPERATION_EDIT';
    public const DELETE = 'CODE_OPERATION_DELETE';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::CREATE, self::EDIT, self::DELETE], true)) {
            return false;
        }

        if ($attribute === self::CREATE) {
            return $subject === null || $subject instanceof CodeOperation;
        }

        return $subject instanceof CodeOperation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        return $this->security->isGranted('ROLE_ADMIN') || $this->security->isGranted('ROLE_COMPTABLE');
    }
}
