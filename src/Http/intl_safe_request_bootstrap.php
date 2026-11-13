<?php

/**
 * Registers IntlSafeRequest as the HttpFoundation factory.
 * Loaded via Composer "files" so it runs before Symfony Runtime creates the Request.
 */

use App\Http\IntlSafeRequest;
use Symfony\Component\HttpFoundation\Request;

Request::setFactory(static function (
    array $query = [],
    array $request = [],
    array $attributes = [],
    array $cookies = [],
    array $files = [],
    array $server = [],
    $content = null,
): Request {
    return new IntlSafeRequest($query, $request, $attributes, $cookies, $files, $server, $content);
});
