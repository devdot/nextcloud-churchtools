<?php

namespace OCA\ChurchToolsIntegration\Jobs;

use CTApi\Models\Common\Tag\Tag;
use CTApi\Models\Groups\Group\Group;
use CTApi\Models\Groups\Person\PersonRequest;
use OCA\ChurchToolsIntegration\Client;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\Constants;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Server;

class Update extends QueuedJob {

	private string $socialLoginPrefix;
	private string $leaderGroupSuffix;
	private string $groupWithFolderTag;
	/**
	 * @var \CTApi\Models\Groups\Person\Person[]
	 */
	private array $ctUsers = [];

	/**
	 * @var \CTApi\Models\Groups\Group\Group[]
	 */
	private array $ctGroups = [];

	/**
	 * @var Tag[]
	 */
	private array $ctGroupTags = [];

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private array $groupFolders = [];

	public function __construct(
		\OCP\AppFramework\Utility\ITimeFactory $time,
		private IConfig $config,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private FolderManager $folderManager,
		private Client $client,
	) {
		parent::__construct($time);

		$this->socialLoginPrefix = $this->config->getSystemValueString('sociallogin_name', 'CT') . '-';
		$this->leaderGroupSuffix = $this->config->getSystemValueString('leader_group_suffix');
		$this->groupWithFolderTag = $this->config->getSystemValueString('group_folder_tag');
	}

	public static function dispatch() {
		$job = Server::get(self::class);
		$list = Server::get(IJobList::class);
		return $job->start($list);
	}

	protected function run($argument) {
		$auth = $this->client->auth();

		if (!$auth) {
			throw new \Exception('CT Auth failed!');
		}

		$this->requestGroups();
		$this->updateGroups();
		$this->updatePersons();
	}

	private function requestGroups(): void {
		try {
			$page = 0;
			$groups = [];
			do {
				$page++;
				$request = $this->client->get('/api/groups?include=tags&page=' . $page, []);
				$return = json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
				$groups[] = $return['data'];
			} while ($return && $return['meta']['pagination']['lastPage'] > $page);
			$groups = array_merge(...$groups);
			
			// now loop through the groups and create objects
			foreach ($groups as $group) {
				$this->ctGroups[$group['id']] = Group::createModelsFromArray($group)[0];
				$this->ctGroupTags[$group['id']] = Tag::createModelsFromArray($group['tags']);
			}
		} catch (\Throwable $e) {
			var_dump($e);
			throw $e;
		}
	}

	private function updateGroups(): void {
		// load the group folders now
		$this->groupFolders = [];
		foreach ($this->folderManager->getAllFolders() as $folder) {
			$this->groupFolders[$folder['mount_point']] = $folder;
		}

		// find all groups with cloud folder
		$groupsWithFolder = [];
		foreach ($this->ctGroups as $id => $group) {
			// loop through tags
			foreach ($this->ctGroupTags[$id] ?? [] as $tag) {
				$tagName = $tag->getName();
				if ($tagName === $this->groupWithFolderTag) {
					$groupsWithFolder[] = $group;
				}
			}
		}

		array_map([$this, 'updateGroupWithFolder'], $groupsWithFolder);
	}

	private function updateGroupWithFolder(Group $group): void {
		// make sure the group is created
		$groupName = $group->getName();
		$ncGroupName = $this->socialLoginPrefix . $groupName;
		if (($ncGroup = $this->groupManager->get($ncGroupName)) === null) {
			$ncGroup = $this->groupManager->createGroup($ncGroupName);
		}

		// always add a leader group here
		$ncLeaderGroup = $this->addLeaderGroup($groupName);
		
		// make sure there is a group folder for this
		$folder = $this->groupFolders[$groupName] ?? null;
		if ($folder === null) {
			// create a group folder here!
			// TODO: perhaps keep a log of this in DB?
			$id = $this->folderManager->createFolder($groupName);
			$folder = $this->folderManager->getFolder($id);
		}

		// and configure that group
		if (count($folder['groups']) === 0) {
			// add our group as default group
			$this->folderManager->addApplicableGroup($folder['id'], $ncGroup->getGID());
			$this->folderManager->setGroupPermissions($folder['id'], $ncGroup->getGID(), Constants::PERMISSION_READ);
			$this->folderManager->addApplicableGroup($folder['id'], $ncLeaderGroup->getGID());
			$this->folderManager->setGroupPermissions($folder['id'], $ncLeaderGroup->getGID(), Constants::PERMISSION_ALL);
			$this->folderManager->setManageAcl($folder['id'], 'group', $ncLeaderGroup->getGID(), true);
			$this->folderManager->setFolderAcl($folder['id'], true);
			$this->folderManager->setManageAcl($folder['id'], 'group', $ncLeaderGroup->getGID(), true);
		}
	}

	private function addLeaderGroup(string $name): IGroup {
		// TODO: maybe keep a record of these groups in a DB?
		$leaderName = $this->socialLoginPrefix . $name . $this->leaderGroupSuffix;
		return $this->groupManager->get($leaderName) ?? $this->groupManager->createGroup($leaderName);
			
	}

	private function getLeaderGroup(string $name): ?IGroup {
		$leaderName = $this->socialLoginPrefix . $name . $this->leaderGroupSuffix;
		return $this->groupManager->get($leaderName);
	}

	private function updatePersons(): void {
		foreach (PersonRequest::all() as $person) {
			$this->ctUsers[$person->getId()] = $person;
		}
		$this->userManager->callForAllUsers(\Closure::fromCallable([$this, 'callbackUpdatePerson']), $this->socialLoginPrefix);
	}

	public function callbackUpdatePerson(IUser $user): void {
		$id = $user->getUID();

		if (!str_starts_with($id, $this->socialLoginPrefix)) {
			return;
		}

		$id = (int)substr($id, strlen($this->socialLoginPrefix));

		// now load the CT User
		$ctUser = $this->ctUsers[$id] ?? null;
		if ($ctUser === null) {
			return;
		}

		// run through sub-job
		UpdatePerson::dispatch($user, $ctUser);
	}
}
