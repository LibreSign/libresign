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

namespace OCA\Libresign\Service\IdentifyMethod;

use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\ClickToSign;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\EmailToken;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\SessionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserSession;

class Email extends AbstractIdentifyMethod {
	public function __construct(
		protected IdentifyMethodService $identifyMethodService,
		private MailService $mail,
		private IdentifyMethodMapper $identifyMethodMapper,
		private IRootFolder $root,
		private ITimeFactory $timeFactory,
		private SessionService $sessionService,
		private FileElementMapper $fileElementMapper,
		private ClickToSign $clickToSign,
		private EmailToken $emailToken,
		private IUserSession $userSession,
	) {
		// TRANSLATORS Name of possible authenticator method. This signalize that the signer could be identified by email
		$this->friendlyName = $this->identifyMethodService->getL10n()->t('Email');
		$this->signatureMethods = [
			$this->clickToSign->getName() => $this->clickToSign,
			$this->emailToken->getName() => $this->emailToken,
		];
		parent::__construct(
			$identifyMethodService,
		);
	}

	public function notify(bool $isNew): void {
		if (!$this->willNotify) {
			return;
		}
		$signRequest = $this->identifyMethodService->getSignRequestMapper()->getById($this->getEntity()->getSignRequestId());
		if ($isNew) {
			$this->mail->notifyUnsignedUser($signRequest, $this->getEntity()->getIdentifierValue());
			return;
		}
		$this->mail->notifySignDataUpdated($signRequest, $this->getEntity()->getIdentifierValue());
	}

	public function validateToRequest(): void {
		$this->throwIfInvalidEmail();
	}

	public function validateToIdentify(): void {
		$this->throwIfAccountAlreadyExists();
		$this->throwIfIsAuthenticatedWithDifferentAccount();
		$this->throwIfInvalidToken();
		$this->throwIfMaximumValidityExpired();
		$this->throwIfRenewalIntervalExpired();
		$this->throwIfNeedToCreateAccount();
		$this->throwIfFileNotFound();
		$this->throwIfAlreadySigned();
		$this->renewSession();
		$this->updateIdentifiedAt();
	}

	protected function throwIfNeedToCreateAccount() {
		$settings = $this->getSettings();
		if (!$settings['can_create_account']) {
			return;
		}
		if ($this->identifyMethodService->getSessionService()->getSignStartTime()) {
			return;
		}
		$email = $this->getEntity()->getIdentifierValue();
		throw new LibresignException(json_encode([
			'action' => JSActions::ACTION_CREATE_USER,
			'settings' => ['accountHash' => md5($email)],
			'message' => $this->identifyMethodService->getL10n()->t('You need to create an account to sign this file.'),
		]));
	}

	private function throwIfIsAuthenticatedWithDifferentAccount(): void {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			return;
		}
		$email = $this->entity->getIdentifierValue();
		if (!empty($user->getEMailAddress()) && $user->getEMailAddress() !== $email) {
			if ($this->getEntity()->getCode() && !$this->getEntity()->getIdentifiedAtDate()) {
				return;
			}
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->identifyMethodService->getL10n()->t('Invalid user')],
			]));
		}
	}

	private function throwIfAccountAlreadyExists(): void {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			return;
		}
		$email = $this->entity->getIdentifierValue();
		$signer = $this->identifyMethodService->getUserManager()->getByEmail($email);
		if (!$signer) {
			return;
		}
		foreach ($signer as $s) {
			if ($s->getUID() === $user->getUID()) {
				return;
			}
		}
		$signRequest = $this->identifyMethodService->getSignRequestMapper()->getById($this->getEntity()->getSignRequestId());
		throw new LibresignException(json_encode([
			'action' => JSActions::ACTION_REDIRECT,
			'errors' => [$this->identifyMethodService->getL10n()->t('User already exists. Please login.')],
			'redirect' => $this->identifyMethodService->getUrlGenerator()->linkToRoute('core.login.showLoginForm', [
				'redirect_url' => $this->identifyMethodService->getUrlGenerator()->linkToRoute(
					'libresign.page.sign',
					['uuid' => $signRequest->getUuid()]
				),
			]),
		]));
	}

	public function validateToCreateAccount(string $value): void {
		$this->throwIfInvalidEmail();
		$this->throwIfNotAllowedToCreateAccount();
		if ($this->identifyMethodService->getUserManager()->userExists($value)) {
			throw new LibresignException($this->identifyMethodService->getL10n()->t('User already exists'));
		}
		if ($this->getEntity()->getIdentifierValue() !== $value) {
			throw new LibresignException($this->identifyMethodService->getL10n()->t('This is not your file'));
		}
	}

	private function throwIfNotAllowedToCreateAccount(): void {
		$settings = $this->getSettings();
		if (!$settings['can_create_account']) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_SHOW_ERROR,
				'errors' => [$this->identifyMethodService->getL10n()->t('It is not possible to create new accounts.')],
			]));
		}
	}

	private function throwIfInvalidEmail(): void {
		if (!filter_var($this->entity->getIdentifierValue(), FILTER_VALIDATE_EMAIL)) {
			throw new LibresignException($this->identifyMethodService->getL10n()->t('Invalid email'));
		}
	}

	public function getSettings(): array {
		if (!empty($this->settings)) {
			return $this->settings;
		}
		$this->settings = parent::getSettingsFromDatabase(
			default: [
				'enabled' => false,
				'can_create_account' => true,
			],
			immutable: [
				'test_url' => $this->identifyMethodService->getUrlGenerator()->linkToRoute('settings.MailSettings.sendTestMail'),
			]
		);
		return $this->settings;
	}
}
