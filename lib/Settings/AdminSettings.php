<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Settings;

use OCA\Libresign\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSettings implements IIconSection {
	public function __construct(
		private IL10N $l,
		private IURLGenerator $urlGenerator,
	) {
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function getID(): string {
		return Application::APP_ID;
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function getName(): string {
		return $this->l->t('LibreSign');
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function getPriority(): int {
		return 60;
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function getIcon(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg');
	}
}
