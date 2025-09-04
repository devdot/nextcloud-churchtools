<?php

declare(strict_types=1);

namespace OCA\ChurchToolsIntegration\AlternativeLogin;

use OCP\Authentication\IAlternativeLogin;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\Util;

/**
 * @psalm-api
 */
class ChurchToolsLogin implements IAlternativeLogin {
	public function __construct(
		private string $appName,
		private IAppConfig $config,
		private IURLGenerator $urlGenerator,
		private IRequest $request,
		private ISession $session,
	) {

	}

	public function getLabel(): string {
		return $this->config->getValueString($this->appName, 'oauth2_login_label');
	}

	public function getLink(): string {
		return $this->urlGenerator->linkToRoute($this->appName . '.oauth2.redirect');
	}

	public function getClass(): string {
		return 'churchtools_integration_login';
	}

	public function load(): void {
		// store redirect
		$this->session->set('login_redirect_url', $this->request->getParam('redirect_url'));

		// make sure the option is enabled
		if (!$this->config->getValueBool($this->appName, 'oauth2_enabled')) {
			Util::addStyle($this->appName, 'hide_oauth2_login');
		}
	}
}
