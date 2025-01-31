<?php

declare(strict_types=1);

namespace OCA\ChurchToolsIntegration\Controller;

use OCA\ChurchToolsIntegration\Jobs\Update;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;

class SettingsController extends Controller {
	/**
	 * @param string $AppName
	 * @param IRequest $request
	 * @param IL10N $l
	 */
	public function __construct(
		$AppName,
		IRequest $request,
		private IL10N $l,
		private IConfig $config,
	) {
		parent::__construct($AppName, $request);
	}

	#[FrontpageRoute('POST', '/settings/run-job')]
	#[AuthorizedAdminSetting(settings: OCA\ChurchToolsIntegration\Settings\Admin::class)]
	#[NoCSRFRequired]
	public function runJob(): JSONResponse {
		try {
			Update::dispatch();
			return new JSONResponse(['message' => 'Job done.']);
		} catch (\Exception $e) {
			return new JSONResponse(['message' => $e->getMessage()]);
		}
	}

	#[FrontpageRoute('PUT', '/settings/admin')]
	#[AuthorizedAdminSetting(settings: OCA\ChurchToolsIntegration\Settings\Admin::class)]
	public function admin(string $url, string $socialLoginName, ?string $userToken): JSONResponse {
		$this->config->setSystemValue('url', $this->sanitizeUrl($url));
		$this->config->setSystemValue('sociallogin_name', $socialLoginName);
		if (!empty($userToken)) {
			$this->config->setSystemValue('user_token', $userToken);
		}

		return new JSONResponse();
	}

	private function sanitizeUrl(string $url): string {
		if (!(str_starts_with($url, 'https://') or str_starts_with($url, 'http://'))) {
			$url = 'https://' . $url;
		}
		if (str_ends_with($url, '/')) {
			$url = substr($url, 0, -1);
		}
		return $url;
	}
}
