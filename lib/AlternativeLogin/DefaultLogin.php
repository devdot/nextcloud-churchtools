<?php

declare(strict_types=1);

namespace OCA\ChurchToolsIntegration\AlternativeLogin;

use OCP\Authentication\IAlternativeLogin;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\Util;

class DefaultLogin implements IAlternativeLogin {
	public function __construct(
		private string $appName,
		private IAppConfig $config,
		private IL10N $l,
	) {

	}

	public function getLabel(): string {
		return $this->l->t('Log in with username or email');
	}

	public function getLink(): string {
		return '#body-login';
	}

	public function getClass(): string {
		return 'churchtools_integration_default_login';
	}

	public function load(): void {
		// make sure the option is enabled
		if ($this->config->getValueBool($this->appName, 'oauth2_hide_default')) {
			Util::addStyle($this->appName, 'hide_default_login');
		} else {
			Util::addStyle($this->appName, 'hide_default_login_button');
		}
	}
}
