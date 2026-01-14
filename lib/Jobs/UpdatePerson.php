<?php

namespace OCA\ChurchToolsIntegration\Jobs;

use CTApi\Models\Groups\GroupTypeRole\GroupTypeRole;
use CTApi\Models\Groups\Person\Person;
use CTApi\Models\Groups\Person\PersonRequest;
use OCA\ChurchToolsIntegration\Client;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Server;

/**
 * @psalm-api
 */
class UpdatePerson extends QueuedJob {
	private string $userPrefix;
	private string $groupPrefix;
	private string $leaderGroupSuffix;

	public function __construct(
		string $appName,
		\OCP\AppFramework\Utility\ITimeFactory $time,
		IAppConfig $config,
		private IGroupManager $groupManager,
		private Client $client,
	) {
		parent::__construct($time);

		$this->userPrefix = $config->getValueString($appName, 'user_prefix');
		$this->groupPrefix = $config->getValueString($appName, 'group_prefix');
		$this->leaderGroupSuffix = $config->getValueString($appName, 'groupfolders_leader_group_suffix');
	}

	public static function dispatch(IUser $user, ?Person $person = null): void {
		$job = Server::get(self::class);
		$job->setArgument([
			'user' => $user,
			'person' => $person,
		]);
		$list = Server::get(IJobList::class);
		$job->start($list);
	}

	/**
	 * @param array{user: IUser, person: ?Person} $argument
	 */
	protected function run($argument) {
		$auth = $this->client->auth();

		if (!$auth) {
			throw new \Exception('CT Auth failed!');
		}

		$user = $argument['user'] ?? throw new \Exception('Missing user!');
		$ctUser = $argument['person'] ?? null;
		if ($ctUser === null) {
			$id = (int)substr($user->getUID(), strlen($this->userPrefix));

			// try to find through search, so it doesn't clutter CT logs/audit trails
			$ctUser = PersonRequest::where('ids', [$id])->get()[0] ?? PersonRequest::find($id);

			if ($ctUser === null) {
				return;
			}
		}

		// add user to groups
		$groups = $ctUser->requestGroups()?->get() ?? [];
		$removeGroups = array_fill_keys($this->groupManager->getUserGroupIds($user), true);
		foreach ($groups as $personGroup) {
			$ctGroup = $personGroup->getGroup();

			$name = $this->groupPrefix . $ctGroup->getName();
			$group = $this->groupManager->get($name);
			if ($group) {
				$group->addUser($user);
				$removeGroups[$name] = false;
			}

			// take care of leader groups
			$leaderName = $name . $this->leaderGroupSuffix;
			if ($this->isGroupLeader($personGroup->getGroupTypeRoleId()) && $leaderGroup = $this->groupManager->get($leaderName)) {
				$leaderGroup->addUser($user);
				$removeGroups[$leaderGroup->getGID()] = false;
			}
		}

		// and remove from groups that are not right
		$removeGroups = array_filter($removeGroups);
		$removeGroups = array_keys($removeGroups);
		foreach ($removeGroups as $groupId) {
			$group = $this->groupManager->get($groupId);
			if ($group === null) {
				continue;
			}
			$group->removeUser($user);
		}
	}

	private function isGroupLeader(int $groupRoleTypeId): bool {
		$groupRoleTypes = $this->client->getGroupRoleTypes();
		$type = $groupRoleTypes[$groupRoleTypeId] ?? null;
		return $type instanceof GroupTypeRole && ($type->getIsLeader() or $type->getName() === 'Administrator');
	}
}
