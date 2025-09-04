<?php

namespace OCA\ChurchToolsIntegration\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

/**
 * @psalm-api
 */
class Section implements IIconSection {
	public function __construct(
		private IL10N $l,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath('churchtools_integration', 'app.svg');
	}

	public function getID(): string {
		return 'churchtools_integration';
	}

	public function getName(): string {
		return $this->l->t('ChurchTools Integration');
	}

	public function getPriority(): int {
		return 50;
	}
}
