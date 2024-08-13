<?php

namespace Glpi\PhpUnit\functional\Glpi\TestTools;

class SubProcessRequest
{
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $GET = [],
        public readonly array $POST = [],
        public readonly array $HEADERS = [],
        public readonly array $login_info = ['user' => null, 'password' => null],
    ) {
    }
}
