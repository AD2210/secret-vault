<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Project;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ProjectVoter extends Voter
{
    public const string VIEW = 'PROJECT_VIEW';
    public const string EDIT = 'PROJECT_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT], true) && $subject instanceof Project;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$subject instanceof Project) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $subject->isAccessibleBy($user),
            self::EDIT => $subject->isManageableBy($user),
            default => false,
        };
    }
}
