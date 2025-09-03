<?php

declare(strict_types=1);

namespace OCA\ChurchToolsIntegration\Controller;

use Devdot\ChurchTools\OAuth2\Client\Provider\ChurchTools;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\Authentication\Token\IProvider;
use OCP\Authentication\Token\IToken;
use OCP\Common\Exception\NotFoundException;
use OCP\IAppConfig;
use OCP\IAvatarManager;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;

class OAuth2Controller extends Controller {
	private bool $enabled;
	private ChurchTools $provider;

	public function __construct(
		string $AppName,
		IRequest $request,
		private IL10N $l,
		private IAppConfig $config,
		private IConfig $userConfig,
		private IURLGenerator $urlGenerator,
		private IUserManager $userManager,
		private IAvatarManager $avatarManager,
		private ISession $session,
		private IUserSession $userSession,
		private IProvider $tokenProvider,
	) {
		parent::__construct($AppName, $request);

		$this->enabled = $this->config->getValueBool($this->appName, 'oauth2_enabled');

		$this->provider = new ChurchTools([
			'url' => $this->config->getValueString($this->appName, 'url'),
			'clientId' => $this->config->getValueString($this->appName, 'oauth2_client_id'),
			'redirectUri' => $this->urlGenerator->linkToRouteAbsolute($this->appName . '.oauth2.login'),
		]);
	}

	#[FrontpageRoute('GET', '/oauth2/redirect')]
	#[PublicPage]
	#[NoCSRFRequired]
	#[UseSession]
	public function redirect() {
		$this->checkEnabledMiddleware();
		return new RedirectResponse($this->provider->getAuthorizationUrl());
	}

	#[FrontpageRoute('GET', '/oauth2/login')]
	#[PublicPage]
	#[NoCSRFRequired]
	#[UseSession]
	public function login(string $code) {
		$this->checkEnabledMiddleware();
		try {
			// attempt to get access tokens
			$tokens = $this->provider->getAccessTokenFromCode($code);

			// get the user that was authenticated
			$oauthUser = $this->provider->getResourceOwner($tokens);

			// inspired by https://github.com/zorn-v/nextcloud-social-login/blob/master/lib/Service/ProviderService.php
			$user = $this->findOrCreateUser($oauthUser);
			$this->updateUser($user, $oauthUser);
			// todo: groups
			$this->loginUser($user);

			return $this->redirectLoggedInUser();
		} catch (IdentityProviderException $e) {
			if ($e->getMessage() === 'invalid_grant') {
				// code is not valid anymore, try again
				header('Location: ' . $this->provider->getAuthorizationUrl());
				exit;
			}

			throw $e;
		}
	}

	private function checkEnabledMiddleware(): void {
		if (!$this->enabled) {
			throw new NotFoundException('Disabled!');
		}
	}

	private function findOrCreateUser(ResourceOwnerInterface $oauthUser): IUser {
		// build username
		$username = $this->config->getValueString($this->appName, 'user_prefix');
		if ($this->config->getValueBool($this->appName, 'oauth2_use_username')) {
			$username .= $oauthUser->toArray()['userName'];
		} else {
			$username .= $oauthUser->getId();
		}

		$user = $this->userManager->get($username);

		// todo allow create?
		// todo: add filters?

		if ($user === null) {
			$password = '341@$a' . substr(base64_encode(random_bytes(64)), 0, 30);
			$user = $this->userManager->createUser($username, $password);

			// todo: default quota?
			// todo: disable_password_confirmation ?
		}

		return $user;
	}

	private function updateUser(IUser $user, ResourceOwnerInterface $oauthUser): void {
		$oauthData = $oauthUser->toArray();
		$user->setDisplayName($oauthData['displayName']);
		$user->setSystemEMailAddress($oauthData['email']);

		// update avatar
		if ($oauthData['photoURL']) {
			try {
				$photo = file_get_contents($oauthData['photoURL']);
				$avatar = $this->avatarManager->getAvatar($user->getUid());
				$avatar->set($photo);
			} catch (\Throwable $e) {
			}
		}
	}

	private function loginUser(IUser $user): void {
		// assuming user session is OC\User\Session https://github.com/nextcloud/server/blob/13210bc7bdc2578d7637ff7339c0db0198bbcef9/lib/private/User/Session.php#L67
		$this->userSession->getSession()->regenerateId();
		$this->userSession->setTokenProvider($this->tokenProvider);
		$this->userSession->createSessionToken($this->request, $user->getUID(), $user->getUID());
		$this->userSession->createRememberMeToken($user);

		$token = $this->tokenProvider->getToken($this->userSession->getSession()->getId());
		$scope = $token->getScopeAsArray();
		$scope[IToken::SCOPE_SKIP_PASSWORD_VALIDATION] = true;
		$token->setScope($scope);
		$this->tokenProvider->updateToken($token);

		$this->userSession->completeLogin($user, [
			'loginName' => $user->getUID(),
			'password' => '',
			'token' => $token,
		], false);

		$user->updateLastLoginTimestamp();
		$this->session->set('last-password-confirm', time());
	}

	private function redirectLoggedInUser(): RedirectResponse {
		if ($redirectUrl = $this->session->get('login_redirect_url')) {
			if (strpos($redirectUrl, '/') === 0) {
				// URL relative to the Nextcloud webroot, generate an absolute one
				$redirectUrl = $this->urlGenerator->getAbsoluteURL($redirectUrl);
			} // else, this is an absolute URL, leave it as-is

			return new RedirectResponse($redirectUrl);
		}

		return new RedirectResponse($this->urlGenerator->getAbsoluteURL('/'));
	}
}
