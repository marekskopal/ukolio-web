<?php

declare(strict_types=1);

namespace Ukolio\Service\Avatar;

use RuntimeException;

/** The uploaded file is not an acceptable avatar (empty, too large, wrong type, undecodable). */
final class InvalidAvatarException extends RuntimeException
{
}
