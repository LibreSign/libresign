<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Activity\Settings;

use OCA\Libresign\Events\SignRequestCanceledEvent;
use OCA\Libresign\Helper\ValidateHelper;
use OCP\IL10N;
use OCP\IUserSession;

class SignRequestCanceled extends LibresignActivitySettings {
	public function __construct(
		protected IL10N $l,
		protected ValidateHelper $validateHelper,
		protected IUserSession $userSession,
	) {
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function getIdentifier(): string {
		return SignRequestCanceledEvent::SIGN_REQUEST_CANCELED;
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function getName(): string {
		return $this->l->t('A signature request has been <strong>canceled</strong>');
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function getPriority(): int {
		return 51;
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function canChangeNotification(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function canChangeMail() {
		return true;
	}
}
