<?php

namespace OCA\ChurchToolsIntegration\Jobs;

use Devdot\ChurchTools\Api\Client;
use Devdot\ChurchTools\Api\Generated\Model\Person;
use Devdot\ChurchTools\Api\Generated\Model\Role;
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
	/**
	 * @var \Devdot\ChurchTools\Api\Generated\Model\Role[]
	 */
	private static array $groupRoles;
	private string $userPrefix;
	private string $groupPrefix;
	private string $leaderGroupSuffix;

	public function __construct(
		string $appName,
		\OCP\AppFramework\Utility\ITimeFactory $time,
		IAppConfig $config,
		private IGroupManager $groupManager,
		private Client $apiClient,
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
	protected function run($argument): void {
		if (!$this->apiClient->isAuthenticated()) {
			throw new \Exception('CT Auth failed!');
		}

		$user = $argument['user'] ?? throw new \Exception('Missing user!');
		$ctUser = $argument['person'] ?? null;
		if ($ctUser === null) {
			$id = (int)substr($user->getUID(), strlen($this->userPrefix));

			// try to find through search, so it doesn't clutter CT logs/audit trails
			try {
				$ctUser = $this->apiClient->getPersonsId($id)->data;

			} catch (\Throwable $t) {
				return;
			}
		}

		// add user to groups
		$groups = $this->apiClient->getPersonsIdGroups($ctUser->id)->data;
		$removeGroups = array_fill_keys($this->groupManager->getUserGroupIds($user), true);
		foreach ($groups as $personGroup) {
			$ctGroup = $personGroup->group;

			$name = $this->groupPrefix . $ctGroup->title;
			$group = $this->groupManager->get($name);
			if ($group) {
				$group->addUser($user);
				$removeGroups[$name] = false;
			}

			// take care of leader groups
			$leaderName = $name . $this->leaderGroupSuffix;
			if ($this->isGroupLeader($personGroup->groupTypeRoleId) && $leaderGroup = $this->groupManager->get($leaderName)) {
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
		$groupRoleTypes = self::getGroupRoles($this->apiClient);
		$role = $groupRoleTypes[$groupRoleTypeId] ?? null;
		return $role instanceof Role && ($role->type === $role->type::Leader || $role->name === 'Administrator');
	}

	/**
	 * @return \Devdot\ChurchTools\Api\Generated\Model\Role[]
	 */
	private static function getGroupRoles(Client $client): array {
		if (!isset(self::$groupRoles)) {
			self::$groupRoles = [];

			$roles = $client->getGroupRoles()->data;
			foreach ($roles as $role) {
				self::$groupRoles[$role->id] = $role;
			}
		}
		return self::$groupRoles;
	}
}
