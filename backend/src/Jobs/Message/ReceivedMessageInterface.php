<?php

declare(strict_types=1);

namespace Ukolio\Jobs\Message;

interface ReceivedMessageInterface
{
	public function getPayload(): string;
}
