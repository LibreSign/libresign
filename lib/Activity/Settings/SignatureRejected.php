<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Activity\Settings;

use OCA\Libresign\Events\SignatureRejectedEvent;
use OCA\Libresign\Helper\ValidateHelper;
use OCP\IL10N;
use OCP\IUserSession;

class SignatureRejected extends LibresignActivitySettings {
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
		return SignatureRejectedEvent::SIGNATURE_REJECTED;
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function getName(): string {
		// TRANSLATORS Activity app setting label for LibreSign events when a signer refuses to sign a document. Keep the <strong> markup.
		return $this->l->t('A signature request has been <strong>rejected</strong>');
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
