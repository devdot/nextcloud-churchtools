<?php

namespace OCA\ChurchToolsIntegration\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;

class Admin implements ISettings {


	public function __construct(
		private IL10N $l,
		private IConfig $config,
		private IInitialState $state,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {
		$this->state->provideInitialState('url', $this->config->getSystemValueString('url', 'https://deine-gemeinde.church.tools'));
		$this->state->provideInitialState('sociallogin_name', $this->config->getSystemValueString('sociallogin_name', 'CT'));
		$this->state->provideInitialState('leader_group_suffix', $this->config->getSystemValueString('leader_group_suffix', ' (L)'));
		$this->state->provideInitialState('group_folder_tag', $this->config->getSystemValueString('group_folder_tag', 'Group-Folder'));

		return new TemplateResponse('churchtools_integration', 'settings-admin');
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
