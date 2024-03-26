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

namespace OCA\Libresign\Service\IdentifyMethod\SignatureMethod;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCP\IUserSession;

class Password extends AbstractSignatureMethod {
	public function __construct(
		protected IdentifyService $identifyService,
		protected Pkcs12Handler $pkcs12Handler,
		private IUserSession $userSession,
	) {
		// TRANSLATORS Name of possible authenticator method. This signalize that the signer could be identified by certificate password
		$this->friendlyName = $this->identifyService->getL10n()->t('Certificate with password');
		parent::__construct(
			$identifyService,
		);
	}

	public function validateToIdentify(): void {
		$pfx = $this->pkcs12Handler->getPfx($this->userSession->getUser()?->getUID());
		openssl_pkcs12_read($pfx, $cert_info, $this->codeSentByUser);
		if (empty($cert_info)) {
			throw new LibresignException($this->identifyService->getL10n()->t('Invalid password'));
		}
	}

	public function toArray(): array {
		$return = parent::toArray();
		$return['hasSignatureFile'] = $this->hasSignatureFile();
		return $return;
	}

	private function hasSignatureFile(): bool {
		try {
			$this->pkcs12Handler->getPfx($this->userSession->getUser()?->getUID());
			return true;
		} catch (\Throwable $th) {
		}
		return false;
	}
}
