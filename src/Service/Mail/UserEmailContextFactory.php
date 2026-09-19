<?php

namespace App\Service\Mail;

use App\Entity\User;

final class UserEmailContextFactory
{
    /** @return array<string, string> */
    public function create(User $user): array
    {
        $email = trim((string) ($user->getEmail() ?? ''));
        $name = trim((string) ($user->getFullName() ?? ''));

        return [
            'user_id' => (string) ($user->getId() ?? ''),
            'user_email' => $email,
            'user_name' => $name !== '' ? $name : $email,
            'user_phone' => trim((string) ($user->getPhone() ?? '')),
        ];
    }
}
