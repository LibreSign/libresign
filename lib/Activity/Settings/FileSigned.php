<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Activity\Settings;

use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ValidateHelper;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;

class FileSigned extends LibresignActivitySettings {
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
		return SignedEvent::FILE_SIGNED;
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function getName(): string {
		return $this->l->t('A document has been <strong>signed</strong>');
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function getPriority(): int {
		return 52;
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function canChangeNotification(): bool {
		if (!$this->userSession->getUser() instanceof IUser) {
			return true;
		}
		try {
			$this->validateHelper->canrequestSign($this->userSession->getUser());
		} catch (LibresignException) {
			return false;
		}
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function canChangeMail() {
		if (!$this->userSession->getUser() instanceof IUser) {
			return true;
		}
		try {
			$this->validateHelper->canrequestSign($this->userSession->getUser());
		} catch (LibresignException) {
			return false;
		}
		return true;
	}
}
