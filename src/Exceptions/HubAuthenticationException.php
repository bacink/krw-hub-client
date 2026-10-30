<?php

declare(strict_types=1);

namespace Krw\HubClient\Exceptions;

class HubAuthenticationException extends HubException
{
    public function __construct(
        string $message = 'Autentikasi ke BKPSDM HUB gagal.',
        private readonly ?int $statusCode = null,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }
}
