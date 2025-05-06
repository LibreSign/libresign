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
	 * {@inheritdoc}
	 */
	public function getIdentifier(): string {
		return 'file_signed';
	}

	/**
	 * {@inheritdoc}
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
