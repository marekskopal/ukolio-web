<?php

declare(strict_types=1);

namespace TaskManager\Dto;

use SensitiveParameter;

/**
 * @implements ArrayFactoryInterface<array{email: string, password: string, name: string}>
 */
final readonly class SignUpDto implements ArrayFactoryInterface
{
    public function __construct(
        #[SensitiveParameter] public string $email,
        #[SensitiveParameter] public string $password,
        public string $name,
    ) {
    }

    public static function fromArray(array $data): static
    {
        return new self(
            email: $data['email'],
            password: $data['password'],
            name: $data['name'],
        );
    }
}
