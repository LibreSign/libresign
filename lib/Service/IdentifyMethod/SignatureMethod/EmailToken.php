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

use OCA\Libresign\Service\IdentifyMethod\IdentifyMethodService;
use Wobeto\EmailBlur\Blur;

class EmailToken extends AbstractSignatureMethod implements IToken {
	public function __construct(
		protected IdentifyMethodService $identifyMethodService,
		protected TokenService $tokenService,
	) {
		// TRANSLATORS Name of possible authenticator method. This signalize that the signer could be identified by email
		$this->friendlyName = $this->identifyMethodService->getL10n()->t('Email token');
		parent::__construct(
			$identifyMethodService,
		);
	}

	public function toArray(): array {
		$return = parent::toArray();
		$entity = $this->getEntity();
		$return['needCode'] = empty($entity->getCode())
			|| empty($entity->getIdentifiedAtDate())
			|| empty($this->codeSentByUser);
		$return['hasConfirmCode'] = !empty($entity->getCode());
		$return['blurredEmail'] = $this->getBlurredEmail();
		$return['hashOfEmail'] = md5($this->getEntity()->getIdentifierValue());
		return $return;
	}

	private function getBlurredEmail(): string {
		$email = $this->getEntity()->getIdentifierValue();
		$blur = new Blur($email);
		return $blur->make();
	}

	public function requestCode(string $identify): void {
		$signRequestMapper = $this->identifyMethodService->getSignRequestMapper();
		$signRequest = $signRequestMapper->getById($this->getEntity()->getSignRequestId());
		$displayName = $signRequest->getDisplayName();
		if ($identify === $displayName) {
			$displayName = '';
		}
		$code = $this->tokenService->sendCodeByEmail($identify, $displayName);
		$this->getEntity()->setCode($code);
		$this->identifyMethodService->save($this->getEntity());
	}
}
