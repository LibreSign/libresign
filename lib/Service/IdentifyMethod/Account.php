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

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\SessionService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\IHasher;
use Psr\Log\LoggerInterface;

class Account extends AbstractIdentifyMethod {
	public const ID = 'account';
	public function __construct(
		private IAppConfig $appConfig,
		private IL10N $l10n,
		private IUserManager $userManager,
		private SignRequestMapper $signRequestMapper,
		private IEventDispatcher $eventDispatcher,
		private IdentifyMethodMapper $identifyMethodMapper,
		private FileMapper $fileMapper,
		private IUserSession $userSession,
		private IURLGenerator $urlGenerator,
		private IRootFolder $root,
		private IHasher $hasher,
		private IUserMountCache $userMountCache,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
		private SessionService $sessionService,
		private MailService $mail
	) {
		// TRANSLATORS Name of possible authenticator method. This signalize that the signer could be identified by Nextcloud acccount
		$this->friendlyName = $this->l10n->t('Account');
		parent::__construct(
			$appConfig,
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
		$this->eventDispatcher->dispatchTyped(new SendSignNotificationEvent(
			$signRequest,
			$this,
			$isNew
		));
	}

	public function validateToRequest(): void {
		$signer = $this->userManager->get($this->entity->getIdentifierValue());
		if (!$signer) {
			throw new LibresignException($this->l10n->t('User not found.'));
		}
	}

	public function validateToSign(): void {
		$signer = $this->getSigner();
		$this->throwIfNotAuthenticated($this->user);
		$this->authenticatedUserIsTheSigner($this->user, $signer);
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
					'errors' => [$this->l10n->t('Invalid user')],
				]));
			}
			$signer = current($signer);
		}
		return $signer;
	}

	private function authenticatedUserIsTheSigner(IUser $user, IUser $signer): void {
		if ($user !== $signer) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->l10n->t('Invalid user')],
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
		$this->settings = $this->getSettingsFromDatabase(
			default: [
				'enabled' => $this->isEnabledByDefault(),
			]
		);
		return $this->settings;
	}

	private function isEnabledByDefault(): bool {
		$config = $this->appConfig->getAppValue('identify_methods', '[]');
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
