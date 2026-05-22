<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Controller\WorkspaceController;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(WorkspaceController::class)]
final class WorkspaceControllerTest extends IntegrationTestCase
{
	public function testListReturnsOnlyMembershipsOfUser(): void
	{
		$user = Fixture::createUser();
		Fixture::createWorkspace($user, 'Mine');

		$other = Fixture::createUser(email: 'other@example.com');
		Fixture::createWorkspace($other, 'Theirs');

		$response = $this->request('GET', '/api/workspaces', authenticatedAs: $user);
		self::assertSame(200, $response->getStatusCode());
		$list = $this->jsonList($response);
		self::assertCount(1, $list);
		self::assertSame('Mine', $list[0]['name']);
	}

	public function testCreateWorkspace(): void
	{
		$user = Fixture::createUser();

		$response = $this->request('POST', '/api/workspaces', body: ['name' => 'Brand new'], authenticatedAs: $user);
		self::assertSame(200, $response->getStatusCode());
		self::assertSame('Brand new', $this->jsonBody($response)['name']);
	}

	public function testCreateWorkspaceRejectsEmptyName(): void
	{
		$user = Fixture::createUser();

		$response = $this->request('POST', '/api/workspaces', body: ['name' => '   '], authenticatedAs: $user);
		self::assertSame(422, $response->getStatusCode());
	}

	public function testOnlyOwnerCanRenameWorkspace(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$admin = Fixture::createUser(email: 'admin@example.com');
		$workspace = Fixture::createWorkspace($owner, 'Original');
		Fixture::addMember($workspace, $admin, WorkspaceRoleEnum::Admin);

		$denied = $this->request(
			'PUT',
			'/api/workspaces/' . $workspace->id,
			body: ['name' => 'New'],
			authenticatedAs: $admin,
		);
		self::assertSame(401, $denied->getStatusCode());

		$ok = $this->request(
			'PUT',
			'/api/workspaces/' . $workspace->id,
			body: ['name' => 'New'],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $ok->getStatusCode());
		self::assertSame('New', $this->jsonBody($ok)['name']);
	}

	public function testTransferOwnership(): void
	{
		$owner = Fixture::createUser(email: 'a@example.com');
		$other = Fixture::createUser(email: 'b@example.com');
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $other, WorkspaceRoleEnum::Admin);

		$response = $this->request(
			'POST',
			'/api/workspaces/' . $workspace->id . '/transfer-ownership',
			body: ['userId' => $other->id],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $response->getStatusCode());

		// Members listing now shows other as Owner, original owner as Admin
		$members = $this->request('GET', '/api/workspaces/' . $workspace->id . '/members', authenticatedAs: $other);
		self::assertSame(200, $members->getStatusCode());
		$byEmail = [];
		foreach ($this->jsonList($members) as $member) {
			assert(is_string($member['email']));
			assert(is_string($member['role']));
			$byEmail[$member['email']] = $member['role'];
		}
		self::assertSame('Owner', $byEmail['b@example.com']);
		self::assertSame('Admin', $byEmail['a@example.com']);
	}

	public function testTransferOwnershipRejectsNonOwner(): void
	{
		$owner = Fixture::createUser(email: 'a@example.com');
		$other = Fixture::createUser(email: 'b@example.com');
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $other, WorkspaceRoleEnum::Admin);

		$response = $this->request(
			'POST',
			'/api/workspaces/' . $workspace->id . '/transfer-ownership',
			body: ['userId' => $owner->id],
			authenticatedAs: $other,
		);
		self::assertSame(401, $response->getStatusCode());
	}

	public function testNonMemberCannotViewMembers(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$intruder = Fixture::createUser(email: 'intruder@example.com');
		$workspace = Fixture::createWorkspace($owner);

		$response = $this->request('GET', '/api/workspaces/' . $workspace->id . '/members', authenticatedAs: $intruder);
		self::assertSame(401, $response->getStatusCode());
	}

	public function testCannotRemoveOwnerDirectly(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);

		$response = $this->request('DELETE', '/api/workspaces/' . $workspace->id . '/members/' . $owner->id, authenticatedAs: $owner);
		self::assertSame(422, $response->getStatusCode());
	}
}
