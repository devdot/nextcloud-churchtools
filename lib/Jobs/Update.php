<?php

namespace OCA\ChurchToolsIntegration\Jobs;

use CTApi\Models\Groups\Group\GroupRequest;
use CTApi\Models\Groups\Person\PersonRequest;
use OCA\ChurchToolsIntegration\Client;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Server;

class Update extends QueuedJob {

    private string $socialLoginPrefix;
    /**
     * @var \CTApi\Models\Groups\Person\Person[]
     */
    private array $ctUsers = [];

	public function __construct(
		\OCP\AppFramework\Utility\ITimeFactory $time,
        private IConfig $config,
        private IUserManager $userManager,
        private IGroupManager $groupManager,
        private Client $client,
	) {
        parent::__construct($time);

        $this->socialLoginPrefix = $this->config->getSystemValueString('sociallogin_name', 'CT') . '-';
	}

    public static function dispatch()
    {
        $job = Server::get(self::class);
        $list = Server::get(IJobList::class);
        return $job->start($list);
    }

	protected function run($argument) {
        $auth = $this->client->auth();

        if (!$auth) {
            throw new \Exception('CT Auth failed!');
        }

        $this->updatePersons();
        $this->updateGroups();
	}

    private function updatePersons(): void
    {
        foreach(PersonRequest::all() as $person) {
            $this->ctUsers[$person->getId()] = $person;
        }
        $this->userManager->callForAllUsers(\Closure::fromCallable([$this, 'callbackUpdatePerson']), $this->socialLoginPrefix);
    }

    public function callbackUpdatePerson(IUser $user): void
    {
        $id = $user->getUID();

        if (!str_starts_with($id, $this->socialLoginPrefix)) {
            return;
        }

        $id = (int) substr($id, strlen($this->socialLoginPrefix));

        // now load the CT User
        $ctUser = $this->ctUsers[$id] ?? null;
        if ($ctUser === null) {
            return;
        }

        // add user to groups
        $groups = $ctUser->requestGroups()->get();
        $removeGroups = array_fill_keys($this->groupManager->getUserGroupIds($user), true);
        foreach($groups as $ctGroup) {
            $name = $this->socialLoginPrefix . $ctGroup->getGroup()->getName();
            $group = $this->groupManager->get($name);
            if ($group) {
                $group->addUser($user);
                $removeGroups[$name] = false;
            }
        }

        // and remove from groups that are not right
        $removeGroups = array_filter($removeGroups);
        foreach ($removeGroups as $groupId) {
            $group = $this->groupManager->get($groupId);
            if ($group === null)
                continue;
            $group->removeUser($user);
        }
    }

    private function updateGroups(): void
    {
    }
}
