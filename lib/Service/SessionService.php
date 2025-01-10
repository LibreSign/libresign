<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
		$renewalInterval = $this->appConfig->setValueInt(Application::APP_ID, 'renewal_interval', self::NO_RENEWAL_INTERVAL);
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
