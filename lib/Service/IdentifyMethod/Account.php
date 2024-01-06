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

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\SessionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class Account extends AbstractIdentifyMethod {
	private bool $canCreateAccount = true;
	public function __construct(
		private IConfig $config,
		private IL10N $l10n,
		private IUserManager $userManager,
		private SignRequestMapper $signRequestMapper,
		private IEventDispatcher $eventDispatcher,
		private IdentifyMethodMapper $identifyMethodMapper,
		private FileMapper $fileMapper,
		private IUserSession $userSession,
		private IURLGenerator $urlGenerator,
		private IRootFolder $root,
		private IUserMountCache $userMountCache,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
		private SessionService $sessionService,
		private MailService $mail
	) {
		// TRANSLATORS Name of possible authenticator method. This signalize that the signer could be identified by Nextcloud acccount
		$this->friendlyName = $this->l10n->t('Account');
		parent::__construct(
			$config,
			$l10n,
			$identifyMethodMapper,
			$signRequestMapper,
			$fileMapper,
			$root,
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
		if ($this->entity->getIdentifierKey() === 'account') {
			$this->eventDispatcher->dispatchTyped(new SendSignNotificationEvent(
				$signRequest,
				$this,
				$isNew
			));
		} elseif ($this->entity->getIdentifierKey() === 'email') {
			if ($isNew) {
				$this->mail->notifyUnsignedUser($signRequest, $this->getEntity()->getIdentifierValue());
				return;
			}
			$this->mail->notifySignDataUpdated($signRequest, $this->getEntity()->getIdentifierValue());
		}
	}

	public function validateToRequest(): void {
		$signer = $this->userManager->get($this->entity->getIdentifierValue());
		if ($signer) {
			return;
		}
		if (!$this->canCreateAccount) {
			throw new LibresignException($this->l10n->t('It is not possible to create new accounts.'));
		}
		if (!filter_var($this->entity->getIdentifierValue(), FILTER_VALIDATE_EMAIL)) {
			throw new LibresignException($this->l10n->t('Invalid email'));
		}
	}

	public function validateToSign(?IUser $user = null): void {
		$this->throwIfNotAuthenticated($user);
		$this->throwIfMaximumValidityExpired();
		$this->throwIfRenewalIntervalExpired();
		if ($this->entity->getIdentifierKey() === 'account') {
			$this->validateWithAccount($user);
		} elseif ($this->entity->getIdentifierKey() === 'email') {
			$this->validateWithEmail($user);
		}
		$this->renewSession();
		$this->updateIdentifiedAt();
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
				'message' => $this->l10n->t('You need to create an account to sign this file.'),
			]));
		}
		if (count($signer) > 0) {
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

	private function throwIfNotAuthenticated(?IUser $user = null): void {
		if (!$user instanceof IUser) {
			$signRequest = $this->signRequestMapper->getById($this->getEntity()->getSignRequestId());
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_REDIRECT,
				'errors' => [$this->l10n->t('You are not logged in. Please log in.')],
				'redirect' => $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
					'redirect_url' => $this->urlGenerator->linkToRoute(
						'libresign.page.sign',
						['uuid' => $signRequest->getUuid()]
					),
				]),
			]));
		}
	}

	public function getSettings(): array {
		if (!empty($this->settings)) {
			return $this->settings;
		}
		$signatureMethod = [
			'id' => 'password',
			'label' => \OC::$server->get(Password::class)->friendlyName,
		];
		$this->settings = $this->getSettingsFromDatabase(
			default: [
				'enabled' => $this->isEnabledByDefault(),
				'signature_method' => $signatureMethod,
				'can_create_account' => $this->canCreateAccount,
				'allowed_signature_methods' => [
					$signatureMethod,
				],
			]
		);
		$this->canCreateAccount = $this->settings['can_create_account'];
		if (in_array('password', $this->settings['allowed_signature_methods'])) {
			$this->settings['allowed_signature_methods'] = [$signatureMethod];
		}
		return $this->settings;
	}

	private function isEnabledByDefault(): bool {
		$config = $this->config->getAppValue(Application::APP_ID, 'identify_methods', '[]');
		$config = json_decode($config, true);
		if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
			return true;
		}

		// Remove not enabled
		$config = array_filter($config, fn ($i) => $i['enabled']);

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
