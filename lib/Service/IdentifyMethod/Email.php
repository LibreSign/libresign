<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod;

use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\ISignatureMethod;
use OCA\Libresign\Service\SessionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class Email extends AbstractIdentifyMethod {
	public array $availableSignatureMethods = [
		ISignatureMethod::SIGNATURE_METHOD_CLICK_TO_SIGN,
		ISignatureMethod::SIGNATURE_METHOD_EMAIL_TOKEN,
	];
	public string $defaultSignatureMethod = ISignatureMethod::SIGNATURE_METHOD_EMAIL_TOKEN;
	public function __construct(
		protected IdentifyService $identifyService,
		private IdentifyMethodMapper $identifyMethodMapper,
		private IRootFolder $root,
		private ITimeFactory $timeFactory,
		private SessionService $sessionService,
		private FileElementMapper $fileElementMapper,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		// TRANSLATORS Name of possible authenticator method. This signalize that the signer could be identified by email
		$this->setFriendlyName($this->identifyService->getL10n()->t('Email'));
		parent::__construct(
			$identifyService,
		);
	}

	#[\Override]
	public function validateToRequest(): void {
		$this->throwIfInvalidEmail();
	}

	#[\Override]
	public function validateToIdentify(): void {
		$this->throwIfAccountAlreadyExists();
		$this->throwIfIsAuthenticatedWithDifferentAccount();
		$this->throwIfMaximumValidityExpired();
		$this->throwIfRenewalIntervalExpired();
		$this->throwIfNeedToCreateAccount();
		$this->throwIfFileNotFound();
		$this->throwIfAlreadySigned();
		$this->renewSession();
		$this->updateIdentifiedAt();
	}

	#[\Override]
	public function validateToSign(): void {
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

	protected function throwIfNeedToCreateAccount(): void {
		$user = $this->userSession->getUser();
		if ($user instanceof IUser) {
			return;
		}
		$settings = $this->getSettings();
		if (!$settings['enabled']) {
			$this->logger->debug('Email identification method is disabled');
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_SHOW_ERROR,
				// TRANSLATORS This signalize that the identification method by email is disabled and the signer can't be identified
				'errors' => [['message' => $this->identifyService->getL10n()->t('Invalid identification method')]],
			]));
			return;
		}
		if (!$settings['can_create_account']) {
			return;
		}
		if ($this->identifyService->getSessionService()->getSignStartTime()) {
			return;
		}
		$email = $this->getEntity()->getIdentifierValue();

		$signer = $this->identifyService->getUserManager()->getByEmail($email);
		if (!$signer) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_CREATE_ACCOUNT,
				'settings' => ['accountHash' => md5($email)],
				'message' => $this->identifyService->getL10n()->t('You need to create an account to sign this file.'),
			]));
		}
		$signRequest = $this->identifyService->getSignRequestMapper()->getById($this->getEntity()->getSignRequestId());
		$errors = [$this->identifyService->getL10n()->t('User already exists. Please login.')];
		if ($this->userSession->isLoggedIn()) {
			$errors[] = $this->identifyService->getL10n()->t('This is not your file');
			$this->userSession->logout();
		}
		throw new LibresignException(json_encode([
			'action' => JSActions::ACTION_REDIRECT,
			'errors' => $errors,
			'redirect' => $this->identifyService->getUrlGenerator()->linkToRoute('core.login.showLoginForm', [
				'redirect_url' => $this->identifyService->getUrlGenerator()->linkToRoute(
					'libresign.page.sign',
					['uuid' => $signRequest->getUuid()]
				),
			]),
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
				'errors' => [['message' => $this->identifyService->getL10n()->t('Invalid user')]],
			]));
		}
	}

	private function throwIfAccountAlreadyExists(): void {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			return;
		}
		$email = $this->entity->getIdentifierValue();
		$signer = $this->identifyService->getUserManager()->getByEmail($email);
		if (!$signer) {
			return;
		}
		foreach ($signer as $s) {
			if ($s->getUID() === $user->getUID()) {
				return;
			}
		}
		$signRequest = $this->identifyService->getSignRequestMapper()->getById($this->getEntity()->getSignRequestId());
		$errors = [$this->identifyService->getL10n()->t('User already exists. Please login.')];
		if ($this->userSession->isLoggedIn()) {
			$errors[] = $this->identifyService->getL10n()->t('This is not your file');
			$this->userSession->logout();
		}
		throw new LibresignException(json_encode([
			'action' => JSActions::ACTION_REDIRECT,
			'errors' => $errors,
			'redirect' => $this->identifyService->getUrlGenerator()->linkToRoute('core.login.showLoginForm', [
				'redirect_url' => $this->identifyService->getUrlGenerator()->linkToRoute(
					'libresign.page.sign',
					['uuid' => $signRequest->getUuid()]
				),
			]),
		]));
	}

	#[\Override]
	public function validateToCreateAccount(string $value): void {
		$this->throwIfInvalidEmail();
		$this->throwIfNotAllowedToCreateAccount();
		if ($this->identifyService->getUserManager()->userExists($value)) {
			throw new LibresignException($this->identifyService->getL10n()->t('User already exists'));
		}
		if ($this->getEntity()->getIdentifierValue() !== $value) {
			throw new LibresignException($this->identifyService->getL10n()->t('This is not your file'));
		}
	}

	private function throwIfNotAllowedToCreateAccount(): void {
		$settings = $this->getSettings();
		if (!$settings['can_create_account']) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_SHOW_ERROR,
				'errors' => [['message' => $this->identifyService->getL10n()->t('It is not possible to create new accounts.')]],
			]));
		}
	}

	private function throwIfInvalidEmail(): void {
		if (!filter_var($this->entity->getIdentifierValue(), FILTER_VALIDATE_EMAIL)) {
			throw new LibresignException($this->identifyService->getL10n()->t('Invalid email'));
		}
	}

	#[\Override]
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
				'test_url' => $this->identifyService->getUrlGenerator()->linkToRoute('settings.MailSettings.sendTestMail'),
			]
		);
		return $this->settings;
	}
}
