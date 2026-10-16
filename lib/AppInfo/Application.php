<?php

declare(strict_types=1);

namespace OCA\ChurchToolsIntegration\AppInfo;

use Devdot\ChurchTools\Api\Client;
use OCA\ChurchToolsIntegration\AlternativeLogin\AlternativeLoginProvider;
use OCA\ChurchToolsIntegration\Listeners\LoginListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IAppConfig;
use OCP\User\Events\PostLoginEvent;
use Psr\Container\ContainerInterface;

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

		// register API Client
		$context->registerService(Client::class, function (ContainerInterface $c): Client {
			/** @var string */
			$appName = $c->get('appName');
			/** @var IAppConfig */
			$appConfig = $c->get(IAppConfig::class);

			$name = $appConfig->getValueString('theming', 'name', 'Nextcloud');
			return Client::create(
				$appConfig->getValueString($appName, 'url'),
				$appConfig->getValueString($appName, 'api_token'),
				['headers' => [
					'User-Agent' => $name . ' (devdot/nextcloud-churchtools)',
				]],
				[],
				[],
				true,
			);
		});

		// register PostLogin for the UpdatePerson Job
		$context->registerEventListener(PostLoginEvent::class, LoginListener::class);

		// register our churchtools login
		$context->registerAlternativeLoginProvider(AlternativeLoginProvider::class);
	}

	public function boot(IBootContext $context): void {
	}
}
