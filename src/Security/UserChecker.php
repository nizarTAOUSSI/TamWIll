<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\DisabledException;
use App\Entity\User;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        // Prevent login for inactive/banned users
        if (! $user->isEnabled()) {
            $ex = new DisabledException('User account is disabled.');
            throw $ex;
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // no-op
    }
}
