<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Activity\Settings;

use OCP\IL10N;

class FileSigned extends LibresignActivitySettings {
	public function __construct(
		protected IL10N $l,
	) {
	}

	/**
	 * @return string Lowercase a-z and underscore only identifier. The type of table activity
	 * @since 20.0.0
	 */
	public function getIdentifier(): string {
		return 'file_signed';
	}

	/**
	 * @return string A translated string
	 * @since 11.0.0
	 */
	public function getName(): string {
		return $this->l->t('A document has been <strong>signed</strong>');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPriority(): int {
		return 52;
	}

}
