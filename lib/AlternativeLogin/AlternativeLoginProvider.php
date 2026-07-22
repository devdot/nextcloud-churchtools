<?php

declare(strict_types=1);

namespace OCA\ChurchToolsIntegration\AlternativeLogin;

use OCP\Authentication\IAlternativeLoginProvider;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;

/**
 * @psalm-suppress UndefinedClass
 */
class AlternativeLoginProvider implements IAlternativeLoginProvider {
	public function __construct(
        private string $appName,
		private IRequest $request,
        private ISession $session,
		private IUrlGenerator $urlGenerator,
		private IAppConfig $appConfig,
		private IL10N $l10n,
	) {
	}

	public function getAlternativeLogins(): array {
        die('heee');
        return [
            new ChurchToolsLogin($this->appName, $this->appConfig, $this->urlGenerator, $this->request, $this->session),
            new DefaultLogin($this->appName, $this->appConfig, $this->l10n),
        ];
	}
}
