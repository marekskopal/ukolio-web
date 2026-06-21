<?php

declare(strict_types=1);

namespace Ukolio\Dto;

/** @implements ArrayFactoryInterface<array{body: string, parentCommentId?: int|null}> */
final readonly class TaskCommentCreateDto implements ArrayFactoryInterface
{
	public function __construct(public string $body, public ?int $parentCommentId = null)
	{
	}

	public static function fromArray(array $data): static
	{
		return new self(body: $data['body'], parentCommentId: $data['parentCommentId'] ?? null);
	}
}
