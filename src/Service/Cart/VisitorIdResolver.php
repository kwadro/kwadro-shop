<?php

namespace App\Service\Cart;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class VisitorIdResolver
{
    public const COOKIE_NAME = 'visitor_id';
    public const REQUEST_ATTRIBUTE = '_visitor_id';
    public const START_VALUE = 1000;

    private const COOKIE_TTL = 31536000;
    private const SEQUENCE_ID = 1;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function resolve(Request $request): string
    {
        if ($request->attributes->has(self::REQUEST_ATTRIBUTE)) {
            return (string) $request->attributes->get(self::REQUEST_ATTRIBUTE);
        }

        $visitorId = (string) $request->cookies->get(self::COOKIE_NAME, '');

        if (self::isValid($visitorId)) {
            $request->attributes->set(self::REQUEST_ATTRIBUTE, $visitorId);

            return $visitorId;
        }

        $visitorId = $this->generateNext();
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $visitorId);

        return $visitorId;
    }

    public function generateNext(): string
    {
        $this->connection->beginTransaction();

        try {
            $lastId = (int) $this->connection->fetchOne(
                'SELECT last_id FROM shop_visitor_id_sequence WHERE id = :id FOR UPDATE',
                ['id' => self::SEQUENCE_ID],
            );

            if ($lastId < self::START_VALUE - 1) {
                $lastId = self::START_VALUE - 1;
            }

            $nextId = $lastId + 1;

            $this->connection->executeStatement(
                'UPDATE shop_visitor_id_sequence SET last_id = :lastId WHERE id = :id',
                ['lastId' => $nextId, 'id' => self::SEQUENCE_ID],
            );

            $this->connection->commit();

            return (string) $nextId;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }
    }

    public static function isValid(string $visitorId): bool
    {
        if ($visitorId === '' || !ctype_digit($visitorId)) {
            return false;
        }

        return (int) $visitorId >= self::START_VALUE;
    }

    public function hasValidCookie(Request $request): bool
    {
        $visitorId = (string) $request->cookies->get(self::COOKIE_NAME, '');

        return self::isValid($visitorId);
    }

    public function shouldAttachCookie(Request $request): bool
    {
        return !$this->hasValidCookie($request) && $request->attributes->has(self::REQUEST_ATTRIBUTE);
    }

    public function attachCookie(Response $response, Request $request, string $visitorId): void
    {
        $response->headers->setCookie(
            Cookie::create(self::COOKIE_NAME, $visitorId)
                ->withExpires(time() + self::COOKIE_TTL)
                ->withPath('/')
                ->withSecure($request->isSecure())
                ->withHttpOnly(true)
                ->withSameSite(Cookie::SAMESITE_LAX)
        );
    }
}
