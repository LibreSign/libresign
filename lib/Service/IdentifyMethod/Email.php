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
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\SessionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\IHasher;
use Psr\Log\LoggerInterface;

class Email extends AbstractIdentifyMethod {
	public const ID = 'email';
	public function __construct(
		private IConfig $config,
		private IL10N $l10n,
		private MailService $mail,
		private SignRequestMapper $signRequestMapper,
		private IdentifyMethodMapper $identifyMethodMapper,
		private FileMapper $fileMapper,
		private IUserManager $userManager,
		private IURLGenerator $urlGenerator,
		private IRootFolder $root,
		private IHasher $hasher,
		private IUserMountCache $userMountCache,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
		private SessionService $sessionService,
		private FileElementMapper $fileElementMapper,
	) {
		// TRANSLATORS Name of possible authenticator method. This signalize that the signer could be identified by email
		$this->friendlyName = $this->l10n->t('Email token');
		parent::__construct(
			$config,
			$l10n,
			$identifyMethodMapper,
			$signRequestMapper,
			$fileMapper,
			$root,
			$hasher,
			$userManager,
			$urlGenerator,
			$userMountCache,
			$timeFactory,
			$logger,
			$sessionService,
		);
		$this->getSettings();
	}

	public function notify(bool $isNew): void {
		if (!$this->willNotify) {
			return;
		}
		$signRequest = $this->signRequestMapper->getById($this->getEntity()->getSignRequestId());
		if ($isNew) {
			$this->mail->notifyUnsignedUser($signRequest, $this->getEntity()->getIdentifierValue());
			return;
		}
		$this->mail->notifySignDataUpdated($signRequest, $this->getEntity()->getIdentifierValue());
	}

	public function validateToRequest(): void {
		$this->throwIfInvalidEmail();
	}

	public function validateToSign(): void {
		$this->throwIfAccountAlreadyExists($this->user);
		$this->throwIfIsAuthenticatedWithDifferentAccount($this->user);
		$this->throwIfInvalidToken();
		$this->throwIfMaximumValidityExpired();
		$this->throwIfRenewalIntervalExpired();
		$this->throwIfNeedToCreateAccount();
		$this->throwIfFileNotFound();
		$this->throwIfAlreadySigned();
		$this->throwIfNeedVisibleElementsAndCanNotHave();
		$this->renewSession();
		$this->updateIdentifiedAt();
	}

	/**
	 * @todo make possible to request to sign by email and use visible elements
	 *
	 * Reference: https://github.com/LibreSign/libresign/issues/2093
	 */
	private function throwIfNeedVisibleElementsAndCanNotHave(): void {
		if ($this->canCreateAccount) {
			return;
		}

		$signRequest = $this->signRequestMapper->getById($this->getEntity()->getSignRequestId());
		$fileEntity = $this->fileMapper->getById($signRequest->getFileId());

		$fileElements = $this->fileElementMapper->getByFileIdAndSignRequestId(
			$fileEntity->getId(),
			$this->getEntity()->getSignRequestId()
		);
		if (!empty($fileElements)) {
			$this->logger->alert('Signer identified by email {email} can not sign the document because neet do have visible elements and is not allowed to create account', ['email' => $this->getEntity()->getIdentifierValue()]);
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->l10n->t('You do not have permission for this action.')],
			]));
		}
	}

	private function throwIfIsAuthenticatedWithDifferentAccount(?IUser $user): void {
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
				'errors' => [$this->l10n->t('Invalid user')],
			]));
		}
	}

	private function throwIfAccountAlreadyExists(?IUser $user): void {
		if (!$user instanceof IUser) {
			return;
		}
		$email = $this->entity->getIdentifierValue();
		$signer = $this->userManager->getByEmail($email);
		if (!$signer) {
			return;
		}
		foreach ($signer as $s) {
			if ($s->getUID() === $user->getUID()) {
				return;
			}
		}
		$signRequest = $this->signRequestMapper->getById($this->getEntity()->getSignRequestId());
		throw new LibresignException(json_encode([
			'action' => JSActions::ACTION_REDIRECT,
			'errors' => [$this->l10n->t('User already exists. Please login.')],
			'redirect' => $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
				'redirect_url' => $this->urlGenerator->linkToRoute(
					'libresign.page.sign',
					['uuid' => $signRequest->getUuid()]
				),
			]),
		]));
	}

	public function validateToCreateAccount(string $value): void {
		$this->throwIfInvalidEmail();
		$this->throwIfNotAllowedToCreateAccount();
		if ($this->userManager->userExists($value)) {
			throw new LibresignException($this->l10n->t('User already exists'));
		}
		if ($this->getEntity()->getIdentifierValue() !== $value) {
			throw new LibresignException($this->l10n->t('This is not your file'));
		}
	}

	private function throwIfNotAllowedToCreateAccount(): void {
		if (!$this->canCreateAccount) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_SHOW_ERROR,
				'errors' => [$this->l10n->t('It is not possible to create new accounts.')],
			]));
		}
	}

	private function throwIfInvalidEmail(): void {
		if (!filter_var($this->entity->getIdentifierValue(), FILTER_VALIDATE_EMAIL)) {
			throw new LibresignException($this->l10n->t('Invalid email'));
		}
	}

	public function getSettings(): array {
		if (!empty($this->settings)) {
			return $this->settings;
		}
		$this->settings = parent::getSettingsFromDatabase(
			default: [
				'enabled' => false,
				'can_create_account' => $this->canCreateAccount,
			],
			immutable: [
				'test_url' => $this->urlGenerator->linkToRoute('settings.MailSettings.sendTestMail'),
			]
		);
		$this->canCreateAccount = $this->settings['can_create_account'];
		return $this->settings;
	}
}
