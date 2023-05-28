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
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\MailService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;

class Account extends AbstractIdentifyMethod {
	private bool $canCreateAccount;
	public function __construct(
		private IConfig $config,
		private IL10N $l10n,
		private IUserManager $userManager,
		private FileUserMapper $fileUserMapper,
		private IEventDispatcher $eventDispatcher,
		private IdentifyMethodMapper $identifyMethodMapper,
		private FileMapper $fileMapper,
		private IUserSession $userSession,
		private IURLGenerator $urlGenerator,
		private IRootFolder $rootFolder,
		private MailService $mail
	) {
		parent::__construct($identifyMethodMapper);
		$this->canCreateAccount = (bool) $this->config->getAppValue(Application::APP_ID, 'can_create_accountApplication', true);
	}

	public function notify(bool $isNew): void {
		$fileUser = $this->fileUserMapper->getById($this->getEntity()->getFileUserId());
		if ($this->entity->getIdentifierKey() === 'account') {
			$this->eventDispatcher->dispatchTyped(new SendSignNotificationEvent(
				$fileUser,
				$this,
				$isNew
			));
		} elseif ($this->entity->getIdentifierKey() === 'email') {
			if ($isNew) {
				$this->mail->notifyUnsignedUser($fileUser, $this->getEntity()->getIdentifierValue());
				return;
			}
			$this->mail->notifySignDataUpdated($fileUser, $this->getEntity()->getIdentifierValue());
		}
	}

	public function validateToRequest(): void {
		if ($this->entity->getIdentifierKey() === 'account') {
			$signer = $this->userManager->get($this->entity->getIdentifierValue());
			if (!$signer) {
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

	public function validateToSign(?IUser $user = null): void {
		$this->requireAuthenticatedUser($user);
		if ($this->entity->getIdentifierKey() === 'account') {
			$this->validateWithAccount($user);
		} elseif ($this->entity->getIdentifierKey() === 'email') {
			$this->validateWithEmail($user);
		}
	}

	private function validateWithAccount(IUser $user): void {
		$signer = $this->getSignerFromAccount();
		$this->authenticatedUserIsTheSigner($user, $signer);
		$this->throwIfAlreadySigned();
		$this->throwIfFileNotFound();
	}

	private function validateWithEmail(IUser $user): void {
		$this->canCreateAccount();
		$signer = $this->getSignerFromEmail();
		$this->authenticatedUserIsTheSigner($user, $signer);
		$this->throwIfAlreadySigned();
		$this->throwIfFileNotFound();
	}

	private function throwIfFileNotFound(): void {
		$fileUser = $this->fileUserMapper->getById($this->getEntity()->getFileUserId());
		$fileEntity = $this->fileMapper->getById($fileUser->getFileId());
		$userFolder = $this->rootFolder->getUserFolder($fileEntity->getUserId());
		$fileToSign = $userFolder->getById($fileEntity->getNodeId());
		if (count($fileToSign) < 1) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->l10n->t('File not found')],
			]));
		}
	}

	private function throwIfAlreadySigned(): void {
		$fileUser = $this->fileUserMapper->getById($this->getEntity()->getFileUserId());
		$fileEntity = $this->fileMapper->getById($fileUser->getFileId());
		if ($fileEntity->getStatus() === FileEntity::STATUS_SIGNED
			|| (!is_null($fileUser) && $fileUser->getSigned())
		) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_SHOW_ERROR,
				'errors' => [$this->l10n->t('File already signed.')],
				'uuid' => $fileEntity->getUuid(),
			]));
		}
	}

	private function getSignerFromAccount(): IUser {
		$account = $this->entity->getIdentifierValue();
		$signer = $this->userManager->get($account);
		if (!$signer) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_SHOW_ERROR,
				'errors' => [$this->l10n->t('User not found.')],
			]));
		}
		return $signer;
	}

	private function getSignerFromEmail(): IUser {
		$email = $this->entity->getIdentifierValue();
		$signer = $this->userManager->getByEmail($email);
		if (!$signer) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_CREATE_USER,
				'settings' => ['accountHash' => md5($email)],
			]));
		}
		if (count($signer) > 0) {
			$fileUser = $this->fileUserMapper->getById($this->getEntity()->getFileUserId());
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_REDIRECT,
				'errors' => [$this->l10n->t('User already exists. Please login.')],
				'redirect' => $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
					'redirect_url' => $this->urlGenerator->linkToRoute(
						'libresign.page.sign',
						['uuid' => $fileUser->getUuid()]
					),
				]),
			]));
		}
		return current($signer);
	}

	private function authenticatedUserIsTheSigner(IUser $user, IUser $signer): void {
		if ($user !== $signer) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->l10n->t('Invalid user')],
			]));
		}
	}

	private function canCreateAccount(): void {
		if (!$this->canCreateAccount) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_SHOW_ERROR,
				'errors' => [$this->l10n->t('It is not possible to create new accounts.')],
			]));
		}
	}

	private function requireAuthenticatedUser(?IUser $user = null): void {
		if (!$user instanceof IUser) {
			$fileUser = $this->fileUserMapper->getById($this->getEntity()->getFileUserId());
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_REDIRECT,
				'errors' => [$this->l10n->t('You are not logged in. Please log in.')],
				'redirect' => $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
					'redirect_url' => $this->urlGenerator->linkToRoute(
						'libresign.page.sign',
						['uuid' => $fileUser->getUuid()]
					),
				]),
			]));
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
