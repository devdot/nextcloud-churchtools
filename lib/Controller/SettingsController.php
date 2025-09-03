<?php

declare(strict_types=1);

namespace OCA\ChurchToolsIntegration\Controller;

use Exception;
use GuzzleHttp\Client;
use JsonException;
use OCA\ChurchToolsIntegration\Jobs\Update;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;

class SettingsController extends Controller {
	public function __construct(
		string $AppName,
		IRequest $request,
		private IL10N $l,
		private IAppConfig $config,
	) {
		parent::__construct($AppName, $request);
	}

	#[FrontpageRoute('POST', '/settings/run-job')]
	#[AuthorizedAdminSetting(settings: OCA\ChurchToolsIntegration\Settings\Admin::class)]
	#[NoCSRFRequired]
	public function runJob(): JSONResponse {
		try {
			Update::dispatch();
			return new JSONResponse(['message' => 'Job done ' . date('H:i:s') . '.']);
		} catch (\Exception $e) {
			return new JSONResponse(['message' => $e->getMessage()]);
		}
	}

	#[FrontpageRoute('PUT', '/settings/set')]
	#[AuthorizedAdminSetting(settings: OCA\ChurchToolsIntegration\Settings\Admin::class)]
	public function set(string $setting, mixed $value): JSONResponse {
		switch ($setting) {
			case 'url':
				assert(is_string($value));
				$value = $this->sanitizeUrl($value);
				return $this->setString($setting, $value);

			case 'user_prefix':
			case 'group_prefix':
			case 'oauth2_client_id':
			case 'oauth2_login_label':
				assert(is_string($value));
				return $this->setString($setting, $value);

			case 'oauth2_enabled':
			case 'oauth2_use_username':
				assert(is_bool($value));
				return $this->setBool($setting, $value);

			default:
				return new JSONResponse([], 400);
		}
	}

	#[FrontpageRoute('POST', '/settings/check_api')]
	#[AuthorizedAdminSetting(settings: OCA\ChurchToolsIntegration\Settings\Admin::class)]
	public function checkApi(): JSONResponse {
		$url = $this->config->getValueString($this->appName, 'url');
		try {
			$client = new Client();
			$request = $client->get($url . '/api/info');
			$response = $request->getBody()->getContents();
			return new JSONResponse([
				'url' => $url,
				'info' => json_decode($response, null, 512, JSON_THROW_ON_ERROR),
			]);
		} catch (JsonException $e) {
			return new JSONResponse([
				'url' => $url,
				'error' => 'Invalid JSON returned from /api/info',
			]);
		} catch (Exception $e) {
			return new JSONResponse([
				'url' => $url,
				'error' => $e->getMessage(),
			]);
		}
	}

	private function setString(string $setting, string $value): JSONResponse {
		$this->config->setValueString($this->appName, $setting, $value);
		return new JSONResponse(['value' => $value]);
	}

	private function setBool(string $setting, bool $value): JSONResponse {
		$this->config->setValueBool($this->appName, $setting, $value);
		return new JSONResponse(['value' => $value]);
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
