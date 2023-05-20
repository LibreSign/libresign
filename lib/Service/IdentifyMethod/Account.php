<?php

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

declare(strict_types=1);

namespace OCA\Libresign\Service\IdentifyMethod;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\MailService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUserManager;

class Account extends AbstractIdentifyMethod {
	private bool $canCreateAccount;
	public function __construct(
		private IConfig $config,
		private IL10N $l10n,
		private IUserManager $userManager,
		protected MailService $mail
	) {
		parent::__construct();
		$this->canCreateAccount = (bool) $this->config->getAppValue(Application::APP_ID, 'can_create_accountApplication', true);
	}

	public function notify(bool $isNew, FileUser $fileUser): void {
		if ($this->entity->getIdentifierKey() === 'uid') {
			/**
			 * @todo Use nextcloud notification service to respect user notification policy
			 */
			$user = $this->userManager->get($this->entity->getIdentifierValue());
			if ($isNew) {
				$this->mail->notifyUnsignedUser($fileUser, $user->getEMailAddress());
				return;
			}
			$this->mail->notifySignDataUpdated($fileUser, $user->getEMailAddress());
		} elseif ($this->entity->getIdentifierKey() === 'email') {
			if ($isNew) {
				$this->mail->notifyUnsignedUser($fileUser, $this->getEntity()->getIdentifierValue());
				return;
			}
			$this->mail->notifySignDataUpdated($fileUser, $this->getEntity()->getIdentifierValue());
		}
	}

	public function validate(): void {
		if ($this->entity->getIdentifierKey() === 'uid') {
			$user = $this->userManager->get($this->entity->getIdentifierValue());
			if (!$user) {
				throw new LibresignException($this->l10n->t('User not found.'));
			}
		} elseif ($this->entity->getIdentifierKey() === 'email') {
			if (!$this->canCreateAccount) {
				throw new LibresignException($this->l10n->t('It is not possible to create new accounts.'));
			}
			if (!filter_var($this->entity->getIdentifierValue(), FILTER_VALIDATE_EMAIL)) {
				throw new LibresignException($this->l10n->t('Invalid email'));
			}
		}
	}

	public function getSettings(): array {
		$settings = parent::getSettings();
		$settings['signature_method'] = 'password';
		$settings['can_create_account'] = $this->canCreateAccount;
		$settings['allowed_signature_methods'] = [
			'password',
		];
		return $settings;
	}
}
