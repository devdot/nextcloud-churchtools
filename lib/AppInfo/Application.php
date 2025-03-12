<?php

declare(strict_types=1);

namespace OCA\ChurchToolsIntegration\AppInfo;

use OCA\ChurchToolsIntegration\Jobs\UpdatePerson;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\User\Events\PostLoginEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'churchtools_integration';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);

		$dispatcher = $this->getContainer()->query(IEventDispatcher::class);
		$dispatcher->addListener(PostLoginEvent::class, function (PostLoginEvent $event) {
			UpdatePerson::dispatch($event->getUser());
		});
	}

	public function register(IRegistrationContext $context): void {
		require_once(__DIR__ . '/../../vendor/autoload.php');
	}

	public function boot(IBootContext $context): void {
	}
}
