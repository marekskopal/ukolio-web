<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{name?: string, locale?: string}> */
final readonly class CurrentUserUpdateDto implements ArrayFactoryInterface
{
	public function __construct(public ?string $name, public ?string $locale)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(name: $data['name'] ?? null, locale: $data['locale'] ?? null);
	}
}
