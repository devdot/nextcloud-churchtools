<?php

declare(strict_types=1);

namespace OCA\ChurchToolsIntegration\AppInfo;

use OCA\ChurchToolsIntegration\AlternativeLogin\AlternativeLoginProvider;
use OCA\ChurchToolsIntegration\Listeners\LoginListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\User\Events\PostLoginEvent;

/**
 * @psalm-api
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'churchtools_integration';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		require_once(__DIR__ . '/../../vendor/autoload.php');
		
		// register PostLogin for the UpdatePerson Job
		$context->registerEventListener(PostLoginEvent::class, LoginListener::class);
		
		// register our churchtools login
		$context->registerAlternativeLoginProvider(AlternativeLoginProvider::class);
	}

	public function boot(IBootContext $context): void {
	}
}
