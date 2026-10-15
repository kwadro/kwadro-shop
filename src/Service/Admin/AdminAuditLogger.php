<?php

namespace App\Service\Admin;

use App\Entity\AdminAuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class AdminAuditLogger
{
    private const MAX_VALUE_LENGTH = 500;

    /** @var list<string> */
    private const SENSITIVE_FIELD_FRAGMENTS = [
        'password',
        'plainpassword',
        'token',
        'secret',
        'apikey',
        'api_key',
        'salt',
        'credential',
    ];

    private ?bool $enabledCache = null;
    private bool $writing = false;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function isEnabled(): bool
    {
        if ($this->enabledCache !== null) {
            return $this->enabledCache;
        }

        try {
            $value = $this->entityManager->getConnection()->fetchOne(
                'SELECT 1 FROM site WHERE log_admin_actions = 1 LIMIT 1'
            );
            $this->enabledCache = (bool) $value;
        } catch (\Throwable) {
            $this->enabledCache = false;
        }

        return $this->enabledCache;
    }

    /**
     * @param list<array{field: string, old: mixed, new: mixed}>|null $changes
     */
    public function log(
        string $action,
        ?object $entity = null,
        ?array $changes = null,
        ?string $accountName = null,
    ): void {
        if ($this->writing || !$this->isEnabled()) {
            return;
        }

        if ($entity instanceof AdminAuditLog) {
            return;
        }

        $account = $accountName ?? $this->resolveAccountName();
        if ($account === '') {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $log = (new AdminAuditLog())
            ->setAccountName($account)
            ->setAction($action)
            ->setIp($request?->getClientIp())
            ->setRequestPath($request?->getRequestUri())
            ->setChanges($changes);

        if ($entity !== null) {
            $log
                ->setEntityClass($this->shortClass($entity::class))
                ->setEntityId($this->resolveEntityId($entity))
                ->setEntityLabel($this->resolveEntityLabel($entity));
        }

        $this->writing = true;
        try {
            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Throwable) {
            // Never break admin actions because of audit logging.
        } finally {
            $this->writing = false;
        }
    }

    public function logLogin(User $user): void
    {
        $this->log(
            AdminAuditLog::ACTION_LOGIN,
            $user,
            null,
            $this->formatAccountName($user),
        );
    }

    /**
     * @return list<array{field: string, old: mixed, new: mixed}>
     */
    public function buildChangeSet(object $entity): array
    {
        if (!$this->entityManager->contains($entity)) {
            return [];
        }

        $uow = $this->entityManager->getUnitOfWork();
        $meta = $this->entityManager->getClassMetadata($entity::class);
        $uow->recomputeSingleEntityChangeSet($meta, $entity);
        $raw = $uow->getEntityChangeSet($entity);

        $changes = [];
        foreach ($raw as $field => [$old, $new]) {
            if ($this->isSensitiveField((string) $field)) {
                $changes[] = [
                    'field' => (string) $field,
                    'old' => '[redacted]',
                    'new' => '[redacted]',
                ];
                continue;
            }

            $oldValue = $this->normalizeValue($old);
            $newValue = $this->normalizeValue($new);
            if ($oldValue === $newValue) {
                continue;
            }

            $changes[] = [
                'field' => (string) $field,
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $changes;
    }

    public function resolveAccountName(): string
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            return $this->formatAccountName($user);
        }

        return $user?->getUserIdentifier() ?? '';
    }

    public function formatAccountName(User $user): string
    {
        $fullName = trim((string) $user->getFullName());
        $email = trim((string) $user->getEmail());
        if ($fullName !== '' && $email !== '' && strcasecmp($fullName, $email) !== 0) {
            return sprintf('%s <%s>', $fullName, $email);
        }

        return $fullName !== '' ? $fullName : ($email !== '' ? $email : $user->getUserIdentifier());
    }

    private function shortClass(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    private function resolveEntityId(object $entity): ?string
    {
        try {
            $ids = $this->entityManager->getClassMetadata($entity::class)->getIdentifierValues($entity);
        } catch (\Throwable) {
            return null;
        }

        if ($ids === []) {
            return null;
        }

        $parts = [];
        foreach ($ids as $value) {
            $parts[] = $this->stringifyScalar($value);
        }

        $joined = implode(':', $parts);

        return $joined !== '' ? $joined : null;
    }

    private function resolveEntityLabel(object $entity): ?string
    {
        if (method_exists($entity, '__toString')) {
            try {
                $label = trim((string) $entity);

                return $label !== '' ? $label : null;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function isSensitiveField(string $field): bool
    {
        $normalized = strtolower(str_replace(['_', '-'], '', $field));
        foreach (self::SENSITIVE_FIELD_FRAGMENTS as $fragment) {
            if (str_contains($normalized, str_replace(['_', '-'], '', $fragment))) {
                return true;
            }
        }

        return false;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null || \is_bool($value) || \is_int($value) || \is_float($value)) {
            return $value;
        }

        if (\is_string($value)) {
            return $this->truncate($value);
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (\is_array($value)) {
            try {
                $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                return '[array]';
            }

            return $this->truncate($encoded);
        }

        if (\is_object($value)) {
            if (method_exists($value, 'getId')) {
                try {
                    $id = $value->getId();
                    if ($id !== null && $id !== '') {
                        return $this->shortClass($value::class).'#'.$this->stringifyScalar($id);
                    }
                } catch (\Throwable) {
                    // fall through
                }
            }

            if (method_exists($value, '__toString')) {
                try {
                    return $this->truncate((string) $value);
                } catch (\Throwable) {
                    return $this->shortClass($value::class);
                }
            }

            return $this->shortClass($value::class);
        }

        return $this->truncate((string) $value);
    }

    private function stringifyScalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return (string) $value;
    }

    private function truncate(string $value): string
    {
        if (mb_strlen($value) <= self::MAX_VALUE_LENGTH) {
            return $value;
        }

        return mb_substr($value, 0, self::MAX_VALUE_LENGTH - 3).'...';
    }
}
