<?php

namespace OCA\ChurchToolsIntegration\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;

class Admin implements ISettings {


	public function __construct(
		private string $appName,
		private IL10N $l,
		private IAppConfig $config,
		private IInitialState $state,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getForm(): TemplateResponse {
		$this->provideInitialStateFromConfigString('url', 'https://deine-gemeinde.church.tools');
		$this->provideInitialStateFromConfigString('user_prefix');
		$this->provideInitialStateFromConfigString('group_prefix');

		$this->provideInitialStateFromConfigBool('oauth2_enabled');
		$this->provideInitialStateFromConfigBool('oauth2_hide_default');
		$this->provideInitialStateFromConfigBool('oauth2_use_username');
		// $this->provideInitialStateFromConfigString('oauth2_client_id'); // dont send the secret
		$this->provideInitialStateFromConfigString('oauth2_login_label', 'Login with ChurchTools');
		$this->state->provideInitialState('oauth2_redirect_uri', $this->urlGenerator->linkToRouteAbsolute($this->appName . '.oauth2.login'));

		$this->provideInitialStateFromConfigBool('api_enabled');
		// $this->provideInitialStateFromConfigString('api_token');

		$this->provideInitialStateFromConfigBool('groupfolders_enabled');
		$this->provideInitialStateFromConfigString('groupfolders_tag', 'Nextcloud');
		$this->provideInitialStateFromConfigString('groupfolders_leader_group_suffix', ' (L)');

		return new TemplateResponse('churchtools_integration', 'settings-admin');
	}

	private function provideInitialStateFromConfigString(string $state, string $default = '', bool $lazy = false): void {
		$this->state->provideInitialState($state, $this->config->getValueString($this->appName, $state, $default, $lazy));
	}

	private function provideInitialStateFromConfigBool(string $state, bool $default = false, bool $lazy = false): void {
		$this->state->provideInitialState($state, $this->config->getValueBool($this->appName, $state, $default, $lazy));
	}

	public function getSection() {
		return 'churchtools_integration'; // Name of the previously created section.
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 *             the admin section. The forms are arranged in ascending order of the
	 *             priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority() {
		return 1;
	}
}
