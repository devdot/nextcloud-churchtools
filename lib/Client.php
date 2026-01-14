<?php

namespace OCA\ChurchToolsIntegration;

use CTApi\CTClient;
use CTApi\CTConfig;
use CTApi\CTLog;
use CTApi\Exceptions\CTAuthException;
use CTApi\Models\Common\Auth\Auth;
use CTApi\Models\Groups\GroupTypeRole\GroupTypeRoleRequest;
use GuzzleHttp\ClientTrait;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use OCP\IAppConfig;
use Psr\Http\Message\RequestInterface;

class Client {
	use ClientTrait;

	private CTConfig $config;
	private CTClient $client;
	private ?Auth $authData = null;

	/**
	 * @var \CTApi\Models\Groups\GroupTypeRole\GroupTypeRole[] $groupRoleTypes
	 * @psalm-suppress PropertyNotSetInConstructor
	 */
	private array $groupRoleTypes;

	public function __construct(
		private string $appName,
		private IAppConfig $ocpConfig,
	) {
		$this->config = CTConfig::createConfig();
		$stack = HandlerStack::create();
		$stack->push(Middleware::mapRequest(function (RequestInterface $request) {
			$name = $this->ocpConfig->getValueString('theming', 'name') ?? 'Nextcloud';
			return $request->withHeader('User-Agent', $name . ' (devdot/nextcloud-churchtools)');
		}));
		$this->client = CTClient::createClient($stack);

		CTLog::enableFileLog(false);
		CTConfig::setConfig($this->config);
		CTConfig::setApiUrl($ocpConfig->getValueString($this->appName, 'url'));
	}


	public function request(string $method, $uri, array $options = []): \Psr\Http\Message\ResponseInterface {
		return $this->client->{$method}($uri, $options);
	}

	public function requestAsync(string $method, $uri, array $options = []): \GuzzleHttp\Promise\PromiseInterface {
		throw new \Exception('NOT IMPLEMENTED');
	}

	public function auth(): ?Auth {
		return $this->authData ??= $this->attemptAuthentication();
	}

	private function attemptAuthentication(): ?Auth {
		CTConfig::setConfig($this->config);
		CTClient::setClient($this->client);

		// attempt login with session
		$session = $this->ocpConfig->getValueString($this->appName, 'api_session', '');
		if (!empty($session)) {
			try {
				$auth = CTConfig::authWithSessionCookie($session);
				$this->storeSession();
				return $auth;
			} catch (CTAuthException $e) {
				// continue
			}
		}

		// attempt login with token
		$token = $this->ocpConfig->getValueString($this->appName, 'api_token', '');
		try {
			$auth = CTConfig::authWithLoginToken($token);
			$this->storeSession();
			return $auth;
		} catch (CTAuthException $e) {
		}

		// all attempts failed
		return null;
	}

	private function storeSession(): void {
		CTConfig::setConfig($this->config);

		$cookie = CTConfig::getSessionCookieString();
		$this->ocpConfig->setValueString($this->appName, 'api_session', $cookie ?? '');
	}

	public function getGroupRoleTypes(): array {
		/** @psalm-suppress RedundantPropertyInitializationCheck */
		return $this->groupRoleTypes ??= $this->requestGroupRoleTypes();
	}

	private function requestGroupRoleTypes(): array {
		$return = [];
		$types = GroupTypeRoleRequest::all();
		foreach ($types as $type) {
			$return[(int)$type->getId()] = $type;
		}
		return $return;
	}
}
