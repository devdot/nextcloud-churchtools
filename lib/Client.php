<?php

namespace OCA\ChurchToolsIntegration;

use CTApi\CTClient;
use CTApi\CTConfig;
use CTApi\CTLog;
use CTApi\Exceptions\CTAuthException;
use CTApi\Models\Common\Auth\Auth;
use GuzzleHttp\ClientTrait;
use OCP\IConfig;

class Client {
	use ClientTrait;

	private CTConfig $config;
	private CTClient $client;
	private ?Auth $authData = null;

	public function __construct(
		private IConfig $ocpConfig,
	) {
		$this->config = CTConfig::createConfig();
		$this->client = CTClient::createClient();

		CTLog::enableFileLog(false);
		CTConfig::setConfig($this->config);
		CTConfig::setApiUrl($ocpConfig->getSystemValueString('url'));
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
		$session = $this->ocpConfig->getSystemValueString('session', '');
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
		$token = $this->ocpConfig->getSystemValueString('user_token', '');
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
		$this->ocpConfig->setSystemValue('session', $cookie);
	}
}
