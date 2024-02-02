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

class EmailToken extends AbstractSignatureMethod {
	public const ID = 'email';
	public function __construct(
		protected IdentifyMethodService $identifyMethodService,
	) {
		// TRANSLATORS Name of possible authenticator method. This signalize that the signer could be identified by email
		$this->friendlyName = $this->identifyMethodService->getL10n()->t('Email token');
		parent::__construct(
			$identifyMethodService,
		);
	}
}
