<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdDoc;

use OCA\Libresign\Db\IdentifyMethodMapper;
use OCP\IUserManager;

/** Resolve the recipients of a rejected ID from the original signer identity. */
class IdDocRejectionRecipientResolver {
	public function __construct(
		private IdentifyMethodMapper $identifyMethods,
		private IUserManager $users,
	) {
	}

	/** @return list<array{uid: ?string, email: ?string}> */
	public function resolve(?string $userId, int $signRequestId): array {
		if ($userId !== null) {
			return [['uid' => $userId, 'email' => $this->users->get($userId)?->getEMailAddress()]];
		}

		$recipients = [];
		foreach ($this->identifyMethods->getIdentifyMethodsFromSignRequestId($signRequestId) as $method) {
			$key = $method->getIdentifierKey();
			if ($key === 'email') {
				$recipients[] = ['uid' => null, 'email' => $method->getIdentifierValue()];
			} elseif ($key === 'account') {
				$uid = $method->getIdentifierValue();
				$recipients[] = ['uid' => $uid, 'email' => $this->users->get($uid)?->getEMailAddress()];
			}
		}
		return $recipients;
	}
}
