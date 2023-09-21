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
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Service\MailService;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;

class Password extends AbstractIdentifyMethod {
	public function __construct(
		private IConfig $config,
		private IL10N $l10n,
		private MailService $mail,
		private FileUserMapper $fileUserMapper,
		private IdentifyMethodMapper $identifyMethodMapper,
		private FileMapper $fileMapper,
		private IUserManager $userManager,
		private IURLGenerator $urlGenerator,
		private IRootFolder $root,
		private IUserMountCache $userMountCache,
		private Pkcs12Handler $pkcs12Handler,
	) {
		// TRANSLATORS Name of possible authenticator method. This signalize that the signer could be identified by certificate password
		$this->friendlyName = $this->l10n->t('Password');
		parent::__construct(
			$config,
			$l10n,
			$identifyMethodMapper,
			$fileUserMapper,
			$fileMapper,
			$root,
			$userMountCache,
		);
	}

	public function validateToSign(?IUser $user = null): void {
		$pfx = $this->pkcs12Handler->getPfx($user->getUID());
		openssl_pkcs12_read($pfx, $cert_info, $this->getEntity()->getIdentifierValue());
		if (empty($cert_info)) {
			throw new LibresignException($this->l10n->t('Invalid password'));
		}
	}
}
