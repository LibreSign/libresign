<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCP\ISession;

class SessionService {
	public const NO_RENEWAL_INTERVAL = 0;
	public const NO_MAXIMUM_VALIDITY = 0;

	public function __construct(
		protected ISession $session,
	) {
	}

	public function getSignStartTime(): int {
		return $this->session->get('libresign-sign-start-time') ?? self::NO_RENEWAL_INTERVAL;
	}

	public function getSessionId(): string {
		if ($this->isAuthenticated()) {
			return $this->session->getId();
		}
		$uuid = $this->session->get('libresign-uuid');
		if (is_string($uuid) && $uuid !== '') {
			// Verification in one browser must not release saved images to another
			// browser holding the same signing link.
			return hash('sha256', $uuid . ':' . $this->session->getId());
		}
		return $this->session->getId();
	}

	public function setIdentifyMethodId(int $id): void {
		$this->session->set('identify_method_id', $id);
	}

	public function getIdentifyMethodId(): ?int {
		$id = $this->session->get('identify_method_id');
		return $id;
	}

	public function resetDurationOfSignPage(int $renewalInterval): void {
		if ($renewalInterval <= self::NO_RENEWAL_INTERVAL) {
			return;
		}
		$this->session->reopen();
		$this->session->set('libresign-sign-start-time', time());
	}

	public function setEmailSignatureKey(string $key): void {
		$this->session->reopen();
		$this->session->set('libresign-email-signature-key', [
			'key' => $key,
			'sessionId' => $this->getSessionId(),
		]);
	}

	public function getEmailSignatureKey(): string {
		$key = $this->session->get('libresign-email-signature-key');
		return is_array($key) && ($key['sessionId'] ?? '') === $this->getSessionId()
			? (string)($key['key'] ?? '') : '';
	}

	public function isAuthenticated(): bool {
		return $this->session->get('user_id') ? true : false;
	}
}
