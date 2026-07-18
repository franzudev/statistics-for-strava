<?php

declare(strict_types=1);

namespace App\Domain\Garmin;

final readonly class GarminOAuthState
{
    private function __construct(
        private string $state,
        private string $codeVerifier,
    ) {
    }

    public static function create(): self
    {
        return new self(
            state: rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='),
            codeVerifier: rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=')
        );
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self((string) $data['state'], (string) $data['codeVerifier']);
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'codeVerifier' => $this->codeVerifier,
        ];
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getCodeVerifier(): string
    {
        return $this->codeVerifier;
    }

    public function getCodeChallenge(): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $this->codeVerifier, true)), '+/', '-_'), '=');
    }
}
