<?php

namespace OCA\ChurchToolsIntegration\Jobs;

use CTApi\Models\Groups\Person\Person;
use CTApi\Models\Groups\Person\PersonRequest;
use OCA\ChurchToolsIntegration\Client;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Server;

class UpdatePerson extends QueuedJob {
	private string $socialLoginPrefix;
	private string $leaderGroupSuffix;
	private string $groupWithFolderTag;

	public function __construct(
		\OCP\AppFramework\Utility\ITimeFactory $time,
		private IConfig $config,
		private IGroupManager $groupManager,
		private Client $client,
	) {
		parent::__construct($time);

		$this->socialLoginPrefix = $this->config->getSystemValueString('sociallogin_name', 'CT') . '-';
		$this->leaderGroupSuffix = $this->config->getSystemValueString('leader_group_suffix');
		$this->groupWithFolderTag = $this->config->getSystemValueString('group_folder_tag');
	}

	public static function dispatch(IUser $user, ?Person $person = null) {
		$job = Server::get(self::class);
		$job->setArgument([
			'user' => $user,
			'person' => $person,
		]);
		$list = Server::get(IJobList::class);
		return $job->start($list);
	}

	protected function run($argument) {
		$auth = $this->client->auth();

		if (!$auth) {
			throw new \Exception('CT Auth failed!');
		}

		$user = $argument['user'] ?? throw new \Exception('Missing user!');
		$ctUser = $argument['person'] ?? null;
		if ($ctUser === null) {
			$id = (int)substr($user->getUID(), strlen($this->socialLoginPrefix));
			$ctUser = PersonRequest::find($id ?? -1);
			if ($ctUser === null) {
				return;
			}
		}

		// add user to groups
		$groups = $ctUser->requestGroups()->get();
		$removeGroups = array_fill_keys($this->groupManager->getUserGroupIds($user), true);
		foreach ($groups as $personGroup) {
			$ctGroup = $personGroup->getGroup();

			$name = $this->socialLoginPrefix . $ctGroup->getName();
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
		foreach ($removeGroups as $groupId => $true) {
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
		return $type && ($type->getIsLeader() or $type->getName() === 'Administrator');
	}
}
