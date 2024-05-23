<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Activity;

use OCP\Activity\ActivitySettings;
use OCP\IL10N;

class FileToSign extends ActivitySettings {
	public function __construct(
		protected IL10N $l,
	) {
	}

	/**
	 * @return string Lowercase a-z and underscore only identifier. The type of table activity
	 * @since 20.0.0
	 */
	public function getIdentifier(): string {
		return 'file_to_sign';
	}

	/**
	 * @return string A translated string
	 * @since 11.0.0
	 */
	public function getName(): string {
		return $this->l->t('You have a <strong>file to sign</strong>');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getGroupIdentifier(): string {
		return 'other';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getGroupName(): string {
		return $this->l->t('Other activities');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPriority(): int {
		return 51;
	}
	/**
	 * {@inheritdoc}
	 */
	public function canChangeNotification(): bool {
		return true;
	}
	/**
	 * {@inheritdoc}
	 */
	public function canChangeMail() {
		return true;
	}
	/**
	 * {@inheritdoc}
	 */
	public function isDefaultEnabledMail() {
		return true;
	}
	/**
	 * {@inheritdoc}
	 */
	public function isDefaultEnabledNotification(): bool {
		return true;
	}
}
