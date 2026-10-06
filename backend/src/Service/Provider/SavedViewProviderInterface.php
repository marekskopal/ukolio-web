<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\SavedView;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;

interface SavedViewProviderInterface
{
	/** @return list<SavedView> */
	public function getViews(Workspace $workspace, User $user): array;

	public function getViewForUser(int $viewId, User $user): ?SavedView;

	public function createView(User $user, Workspace $workspace, string $name, string $filterConfig): SavedView;

	public function updateView(SavedView $view, string $name, string $filterConfig): SavedView;

	public function deleteView(SavedView $view): void;
}
