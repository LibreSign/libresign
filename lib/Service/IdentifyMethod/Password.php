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
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\SessionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Security\IHasher;
use Psr\Log\LoggerInterface;

class Password extends AbstractIdentifyMethod {
	public const ID = 'password';
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
		private Pkcs12Handler $pkcs12Handler,
	) {
		// TRANSLATORS Name of possible authenticator method. This signalize that the signer could be identified by certificate password
		$this->friendlyName = $this->l10n->t('Certificate with password');
		parent::__construct(
			$config,
			$l10n,
			$identifyMethodMapper,
			$signRequestMapper,
			$fileMapper,
			$root,
			$hasher,
			$userMountCache,
			$timeFactory,
			$logger,
			$sessionService,
		);
	}

	public function validateToSign(): void {
		$pfx = $this->pkcs12Handler->getPfx($this->user->getUID());
		openssl_pkcs12_read($pfx, $cert_info, $this->getEntity()->getIdentifierValue());
		if (empty($cert_info)) {
			throw new LibresignException($this->l10n->t('Invalid password'));
		}
	}

	public function getSettings(): array {
		if (!empty($this->settings)) {
			return $this->settings;
		}

		if (!$this->sessionService->isAuthenticated()) {
			$isEnabledAsSignatueMethod = false;
		} else {
			$config = $this->config->getAppValue(Application::APP_ID, 'signature_methods', '[]');
			$config = json_decode($config, true);
			if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
				$isEnabledAsSignatueMethod = true;
			} else {
				$isEnabledAsSignatueMethod = array_reduce($config, function (bool $carry, $method) {
					if (!is_array($method)) {
						$carry = false;
					} elseif (array_key_exists('enabled', $method)) {
						$carry = ((bool) $method['enabled']) || !$carry;
					}
					return $carry;
				}, true);
			}
		}

		$this->settings = $this->getSettingsFromDatabase(
			default: [
				'enabled_as_signature_method' => $isEnabledAsSignatueMethod,
			]
		);

		return $this->settings;
	}
}
