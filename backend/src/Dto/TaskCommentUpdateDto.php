<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{body: string}> */
final readonly class TaskCommentUpdateDto implements ArrayFactoryInterface
{
	public function __construct(public string $body)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(body: $data['body']);
	}
}
