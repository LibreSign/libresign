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

use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\MailService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;

class Email extends AbstractIdentifyMethod {
	public function __construct(
		private IL10N $l10n,
		private MailService $mail,
		private FileUserMapper $fileUserMapper,
		private IdentifyMethodMapper $identifyMethodMapper,
		private IUserManager $userManager,
		private IURLGenerator $urlGenerator
	) {
		parent::__construct($identifyMethodMapper);
	}

	public function notify(bool $isNew): void {
		$fileUser = $this->fileUserMapper->getById($this->getEntity()->getFileUserId());
		if ($isNew) {
			$this->mail->notifyUnsignedUser($fileUser, $this->getEntity()->getIdentifierValue());
			return;
		}
		$this->mail->notifySignDataUpdated($fileUser, $this->getEntity()->getIdentifierValue());
	}

	public function validateToRequest(): void {
		if (!filter_var($this->entity->getIdentifierValue(), FILTER_VALIDATE_EMAIL)) {
			throw new LibresignException($this->l10n->t('Invalid email'));
		}
	}

	public function validateToCreateAccount(string $value): void {
		$this->validateToRequest();
		if ($this->userManager->userExists($value)) {
			throw new LibresignException($this->l10n->t('User already exists'));
		}
		if ($this->getEntity()->getIdentifierValue() !== $value) {
			throw new LibresignException($this->l10n->t('This is not your file'));
		}
	}

	public function getSettings(): array {
		$settings = parent::getSettings();
		$settings['test_url'] = $this->urlGenerator->linkToRoute('settings.MailSettings.sendTestMail');
		return $settings;
	}
}
