<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity\Enum;

enum WorkspaceRoleEnum: string
{
	case Owner = 'Owner';
	case Member = 'Member';
}
