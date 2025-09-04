<?php

namespace OCA\ChurchToolsIntegration\Jobs;

use CTApi\Models\Common\Tag\Tag;
use CTApi\Models\Groups\Group\Group;
use CTApi\Models\Groups\Person\PersonRequest;
use Exception;
use OCA\ChurchToolsIntegration\Client;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Server;

/**
 * @psalm-api
 */
class Update extends QueuedJob {

	private string $userPrefix;
	private string $groupPrefix;
	private string $leaderGroupSuffix;
	private string $groupWithFolderTag;
	private int $rootStorageId;
	/**
	 * @var \CTApi\Models\Groups\Person\Person[]
	 */
	private array $ctUsers = [];

	/**
	 * @var \CTApi\Models\Groups\Group\Group[]
	 */
	private array $ctGroups = [];

	/**
	 * @var Tag[][]
	 */
	private array $ctGroupTags = [];

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private array $groupFolders = [];

	public function __construct(
		private string $appName,
		\OCP\AppFramework\Utility\ITimeFactory $time,
		private IAppConfig $config,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private FolderManager $folderManager,
		private RuleManager $ruleManager,
		IRootFolder $rootFolder,
		private readonly IDBConnection $connection,
		private Client $client,
	) {
		parent::__construct($time);

		$this->userPrefix = $this->config->getValueString($this->appName, 'user_prefix');
		$this->groupPrefix = $this->config->getValueString($this->appName, 'group_prefix');
		$this->leaderGroupSuffix = $this->config->getValueString($this->appName, 'groupfolders_leader_group_suffix');
		$this->groupWithFolderTag = $this->config->getValueString($this->appName, 'groupfolders_tag');

		$this->rootStorageId = $rootFolder->getMountPoint()->getNumericStorageId() ?? -1;
	}

	public static function dispatch(): void {
		$job = Server::get(self::class);
		$list = Server::get(IJobList::class);
		$job->start($list);
	}

	/**
	 * @param array $argument
	 */
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
				/** @psalm-suppress InvalidPropertyAssignmentValue */
				$this->ctGroupTags[$group['id']] = Tag::createModelsFromArray($group['tags']);
			}
		} catch (\Throwable $e) {
			throw $e;
		}
	}

	private function updateGroups(): void {
		if (!$this->config->getValueBool($this->appName, 'groupfolders_enabled')) {
			return;
		}

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
		$groupName = $group->getName() ?? '';
		$ncGroupName = $this->groupPrefix . $groupName;
		if (($ncGroup = $this->groupManager->get($ncGroupName)) === null) {
			$ncGroup = $this->groupManager->createGroup($ncGroupName) ?? throw new Exception('Could not create group ' . $ncGroupName);
		}

		// always add a leader group here
		$ncLeaderGroup = $this->addLeaderGroup($groupName);

		// make sure there is a group folder for this
		$folder = $this->groupFolders[$groupName] ?? null;
		if ($folder === null) {
			// create a group folder here!
			// TODO: perhaps keep a log of this in DB?
			$id = $this->folderManager->createFolder($groupName);
			$folder = $this->folderManager->getFolder($id) ?? throw new Exception('Folder does not exist #' . $id);
		}

		// and configure that group
		if (count($folder['groups']) === 0) {
			// add our group as default group
			$this->folderManager->addApplicableGroup($folder['id'], $ncGroup->getGID());
			$this->folderManager->setGroupPermissions($folder['id'], $ncGroup->getGID(), Constants::PERMISSION_ALL);
			$this->folderManager->addApplicableGroup($folder['id'], $ncLeaderGroup->getGID());
			$this->folderManager->setGroupPermissions($folder['id'], $ncLeaderGroup->getGID(), Constants::PERMISSION_ALL);
			$this->folderManager->setManageAcl($folder['id'], 'group', $ncLeaderGroup->getGID(), true);
			$this->folderManager->setFolderAcl($folder['id'], true);
			$this->folderManager->setManageAcl($folder['id'], 'group', $ncLeaderGroup->getGID(), true);
		}

		// set ACL for those folders
		$path = '__groupfolders/' . $folder['id'];
		/** @psalm-suppress MissingDependency */
		$rules = $this->ruleManager->getAllRulesForPaths($this->rootStorageId, [$path]);
		if (count($rules) === 0) {
			// find the file id
			$query = $this->connection->getQueryBuilder();
			$query->select(['fileid'])
				->from('filecache')
				->where($query->expr()->eq('path_hash', $query->createNamedParameter(md5($path))))
				->andWhere($query->expr()->eq('storage', $query->createNamedParameter($this->rootStorageId)));
			$fileId = (int)$query->executeQuery()->fetch(\PDO::FETCH_COLUMN);
			$this->setAclForGroupFolder($ncGroup, $fileId, 1);
			$this->setAclForGroupFolder($ncLeaderGroup, $fileId, 31);
		}
	}

	private function setAclForGroupFolder(IGroup $group, int $fileId, int $permissions, int $mask = 31): void {
		$query = $this->connection->getQueryBuilder();
		$query->insert('group_folders_acl')->values([
			'fileid' => $fileId,
			'mapping_type' => '\'group\'',
			'mapping_id' => $query->createNamedParameter($group->getGID()),
			'mask' => $mask,
			'permissions' => $permissions,
		]);
		$query->executeStatement();
	}

	private function addLeaderGroup(string $name): IGroup {
		// TODO: maybe keep a record of these groups in a DB?
		$leaderName = $this->groupPrefix . $name . $this->leaderGroupSuffix;
		return $this->groupManager->get($leaderName) ?? $this->groupManager->createGroup($leaderName) ?? throw new Exception('Could not create new group ' . $leaderName);

	}

	private function updatePersons(): void {
		foreach (PersonRequest::all() as $person) {
			$this->ctUsers[$person->getId()] = $person;
		}
		$this->userManager->callForAllUsers(\Closure::fromCallable([$this, 'callbackUpdatePerson']), $this->userPrefix);
	}

	public function callbackUpdatePerson(IUser $user): void {
		$id = $user->getUID();

		if (!str_starts_with($id, $this->userPrefix)) {
			return;
		}

		$id = (int)substr($id, strlen($this->userPrefix));

		// now load the CT User
		$ctUser = $this->ctUsers[$id] ?? null;
		if ($ctUser === null) {
			return;
		}

		// run through sub-job
		UpdatePerson::dispatch($user, $ctUser);
	}
}
