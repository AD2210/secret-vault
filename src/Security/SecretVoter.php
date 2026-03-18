<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Secret;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class SecretVoter extends Voter
{
    public const string VIEW = 'SECRET_VIEW';
    public const string EDIT = 'SECRET_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT], true) && $subject instanceof Secret;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$subject instanceof Secret) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $project = $subject->getProject();

        return null !== $project && ($project->hasMember($user) || $project->getCreatedBy() === $user);
    }
}
