<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Controller\ScriptController;
use Ukolio\Model\Entity\Enum\ScriptRunStatusEnum;
use Ukolio\Model\Entity\Enum\ScriptTriggerEnum;
use Ukolio\Model\Entity\Script;
use Ukolio\Model\Entity\ScriptRun;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\ScriptRunRepository;
use Ukolio\Service\Script\ScriptProviderInterface;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(ScriptController::class)]
final class ScriptControllerTest extends IntegrationTestCase
{
	public function testListReturnsRunCountAndLastStatus(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$script = $this->createScript($user, $workspace);

		$this->persistRun($script, ScriptRunStatusEnum::Success, 1, 3);
		$this->persistRun($script, ScriptRunStatusEnum::Error, 2, 7);

		$response = $this->request('GET', '/api/workspaces/' . $workspace->id . '/scripts', null, $user);
		self::assertSame(200, $response->getStatusCode());

		$scripts = $this->jsonList($response);
		self::assertCount(1, $scripts);
		self::assertSame(2, $scripts[0]['runCount']);
		// Latest persisted run wins.
		self::assertSame('Error', $scripts[0]['lastStatus']);
	}

	public function testRunsEndpointExposesCallCounts(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$script = $this->createScript($user, $workspace);

		$this->persistRun($script, ScriptRunStatusEnum::Success, 5, 42);

		$response = $this->request('GET', '/api/scripts/' . $script->id . '/runs', null, $user);
		self::assertSame(200, $response->getStatusCode());

		$runs = $this->jsonList($response);
		self::assertCount(1, $runs);
		self::assertSame(5, $runs[0]['httpCalls']);
		self::assertSame(42, $runs[0]['taskApiCalls']);
	}

	private function createScript(User $user, Workspace $workspace): Script
	{
		$provider = AppHarness::container()->get(ScriptProviderInterface::class);
		assert($provider instanceof ScriptProviderInterface);

		return $provider->create($user, $workspace, 'Digest', 'ukolio.log("hi");', ScriptTriggerEnum::Manual, null, true);
	}

	private function persistRun(Script $script, ScriptRunStatusEnum $status, int $httpCalls, int $taskApiCalls): void
	{
		$now = new DateTimeImmutable();
		$run = new ScriptRun(
			script: $script,
			triggerType: ScriptTriggerEnum::Manual,
			status: $status,
			startedAt: $now,
			finishedAt: $now,
			httpCalls: $httpCalls,
			taskApiCalls: $taskApiCalls,
		);
		$run->createdAt = $now;
		$run->updatedAt = $now;

		$repository = AppHarness::container()->get(ScriptRunRepository::class);
		assert($repository instanceof ScriptRunRepository);
		$repository->persist($run);
	}
}
