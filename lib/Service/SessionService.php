<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCP\IAppConfig;
use OCP\ISession;

class SessionService {
	public const NO_RENEWAL_INTERVAL = 0;
	public const NO_MAXIMUM_VALIDITY = 0;

	public function __construct(
		protected ISession $session,
		protected IAppConfig $appConfig,
	) {
	}

	public function getSignStartTime(): int {
		return $this->session->get('libresign-sign-start-time') ?? self::NO_RENEWAL_INTERVAL;
	}

	public function getSessionId(): string {
		return $this->session->getId();
	}

	public function setIdentifyMethodId(int $id): void {
		$this->session->set('identify_method_id', $id);
	}

	public function getIdentifyMethodId(): ?int {
		$id = $this->session->get('identify_method_id');
		return $id;
	}

	public function resetDurationOfSignPage(): void {
		$renewalInterval = $this->appConfig->getValueInt(Application::APP_ID, 'renewal_interval', self::NO_RENEWAL_INTERVAL);
		if ($renewalInterval <= self::NO_RENEWAL_INTERVAL) {
			return;
		}
		$this->session->reopen();
		$this->session->set('libresign-sign-start-time', time());
	}

	public function isAuthenticated(): bool {
		return $this->session->get('user_id') ? true : false;
	}
}
