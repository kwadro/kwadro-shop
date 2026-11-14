<?php

namespace App\Service\User;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Mail\UserInviteMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class UserInviteService
{
    private const TOKEN_TTL_DAYS = 14;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserInviteMailer $userInviteMailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Issues a one-time invite link and emails it. Returns the absolute URL.
     */
    public function issueAndSend(User $user, string $locale = 'ua'): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $user->setInvitationTokenHash(hash('sha256', $rawToken));
        $user->setInvitationExpiresAt(new \DateTimeImmutable('+' . self::TOKEN_TTL_DAYS . ' days'));

        if ($user->getPassword() === null || $user->getPassword() === '') {
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(24))));
        }

        $this->entityManager->flush();

        $inviteUrl = $this->buildInviteUrl($user, $rawToken, $locale);
        $this->userInviteMailer->send($user, $inviteUrl, $locale);

        return $inviteUrl;
    }

    public function findUserByInviteToken(string $encoded): ?User
    {
        $parts = explode('.', $encoded, 2);
        if (\count($parts) !== 2) {
            return null;
        }

        [$encodedUserId, $rawToken] = $parts;
        $userId = $this->decodeUserId($encodedUserId);
        if ($userId === null || $rawToken === '') {
            return null;
        }

        $user = $this->userRepository->find($userId);
        if (!$user instanceof User) {
            return null;
        }

        $hash = $user->getInvitationTokenHash();
        if ($hash === null || !hash_equals($hash, hash('sha256', $rawToken))) {
            return null;
        }

        $expiresAt = $user->getInvitationExpiresAt();
        if (!$expiresAt instanceof \DateTimeImmutable || $expiresAt <= new \DateTimeImmutable()) {
            return null;
        }

        return $user;
    }

    public function activate(User $user, string $plainPassword): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->clearInvitation();
        $user->eraseCredentials();
        $this->entityManager->flush();
    }

    private function buildInviteUrl(User $user, string $rawToken, string $locale): string
    {
        $userId = $user->getId();
        if ($userId === null) {
            throw new \RuntimeException('Cannot invite a user without an id.');
        }

        $token = $this->encodeUserId($userId) . '.' . $rawToken;

        return $this->urlGenerator->generate(
            'app_invite_activate',
            ['_locale' => $locale, 'token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    private function encodeUserId(int $userId): string
    {
        return rtrim(strtr(base64_encode((string) $userId), '+/', '-_'), '=');
    }

    private function decodeUserId(string $encoded): ?int
    {
        $padded = strtr($encoded, '-_', '+/');
        $remainder = \strlen($padded) % 4;
        if ($remainder > 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($padded, true);
        if ($decoded === false || !ctype_digit($decoded)) {
            return null;
        }

        $id = (int) $decoded;

        return $id > 0 ? $id : null;
    }
}
