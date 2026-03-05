<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\ISignatureMethod;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\SessionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\IHasher;
use Psr\Log\LoggerInterface;

class Account extends AbstractIdentifyMethod {
	public array $availableSignatureMethods = [
		ISignatureMethod::SIGNATURE_METHOD_CLICK_TO_SIGN,
		ISignatureMethod::SIGNATURE_METHOD_EMAIL_TOKEN,
		ISignatureMethod::SIGNATURE_METHOD_PASSWORD,
	];
	public string $defaultSignatureMethod = ISignatureMethod::SIGNATURE_METHOD_PASSWORD;
	public function __construct(
		protected IdentifyService $identifyService,
		private IUserManager $userManager,
		private IEventDispatcher $eventDispatcher,
		private IdentifyMethodMapper $identifyMethodMapper,
		private IUserSession $userSession,
		private IURLGenerator $urlGenerator,
		private IRootFolder $root,
		private IHasher $hasher,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
		private SessionService $sessionService,
		private MailService $mail,
	) {
		// TRANSLATORS Name of possible authenticator method. This signalize that the signer could be identified by Nextcloud acccount
		$this->setFriendlyName($this->identifyService->getL10n()->t('Account'));
		parent::__construct(
			$identifyService,
		);
	}

	#[\Override]
	public function validateToRequest(): void {
		$signer = $this->getSigner();
		$this->validateSignatureMethodsForRequest($signer);
	}

	private function validateSignatureMethodsForRequest(IUser $signer): void {
		foreach ($this->getSignatureMethods() as $signatureMethod) {
			if (!$signatureMethod->isEnabled()) {
				continue;
			}
			if ($signatureMethod->getName() === ISignatureMethod::SIGNATURE_METHOD_EMAIL_TOKEN) {
				$this->validateEmailForEmailToken($signer);
			}
		}
	}

	private function validateEmailForEmailToken(IUser $signer): void {
		$email = $signer->getEMailAddress();
		if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			throw new LibresignException(
				$this->identifyService->getL10n()->t('Signer without valid email address')
			);
		}
	}

	#[\Override]
	public function validateToIdentify(): void {
		$signer = $this->getSigner();
		$this->throwIfNotAuthenticated();
		$this->authenticatedUserIsTheSigner($signer);
		$this->throwIfMaximumValidityExpired();
		$this->throwIfRenewalIntervalExpired();
		$this->throwIfAlreadySigned();
		$this->throwIfFileNotFound();
		$this->renewSession();
		$this->updateIdentifiedAt();
	}

	#[\Override]
	public function validateToSign(): void {
		$signer = $this->getSigner();
		$this->throwIfNotAuthenticated();
		$this->authenticatedUserIsTheSigner($signer);
		$this->throwIfInvalidToken();
		$this->throwIfMaximumValidityExpired();
		$this->throwIfRenewalIntervalExpired();
		$this->throwIfAlreadySigned();
		$this->throwIfFileNotFound();
		$this->renewSession();
		$this->updateIdentifiedAt();
	}

	private function getSigner(): IUser {
		$identifierValue = $this->entity->getIdentifierValue();
		$signer = $this->userManager->get($identifierValue);
		if (!$signer) {
			$signer = $this->userManager->getByEmail($identifierValue);
			if (empty($signer) || count($signer) > 1) {
				throw new LibresignException(json_encode([
					'action' => JSActions::ACTION_DO_NOTHING,
					'errors' => [['message' => $this->identifyService->getL10n()->t('Invalid user')]],
				]));
			}
			$signer = current($signer);
		}
		return $signer;
	}

	private function authenticatedUserIsTheSigner(IUser $signer): void {
		if ($this->userSession->getUser() !== $signer) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [['message' => $this->identifyService->getL10n()->t('Invalid user')]],
			]));
		}
	}

	private function throwIfNotAuthenticated(): void {
		if (!$this->userSession->getUser() instanceof IUser) {
			$signRequest = $this->identifyService->getSignRequestMapper()->getById($this->getEntity()->getSignRequestId());
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_REDIRECT,
				'errors' => [$this->identifyService->getL10n()->t('You are not logged in. Please log in.')],
				'redirect' => $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
					'redirect_url' => $this->urlGenerator->linkToRoute(
						'libresign.page.sign',
						['uuid' => $signRequest->getUuid()]
					),
				]),
			]));
		}
	}

	#[\Override]
	public function getSettings(): array {
		if (!empty($this->settings)) {
			return $this->settings;
		}
		$this->settings = $this->getSettingsFromDatabase(
			default: [
				'enabled' => $this->isEnabledByDefault(),
			]
		);
		return $this->settings;
	}

	private function isEnabledByDefault(): bool {
		$config = $this->identifyService->getAppConfig()->getValueArray(Application::APP_ID, 'identify_methods', []);
		if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
			return true;
		}

		// Remove not enabled
		$config = array_filter($config, fn ($i) => isset($i['enabled']) && $i['enabled'] ? true : false);

		$current = array_reduce($config, function ($carry, $config) {
			if ($config['name'] === $this->name) {
				return $config;
			}
			return $carry;
		}, []);

		$total = count($config);

		if ($total === 0) {
			return true;
		}

		if ($total === 1 && !empty($current)) {
			return true;
		}
		return false;
	}
}
