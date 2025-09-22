<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod\SignatureMethod;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Vendor\Wobeto\EmailBlur\Blur;

class EmailToken extends AbstractSignatureMethod implements IToken {
	public function __construct(
		protected IdentifyService $identifyService,
		protected TokenService $tokenService,
	) {
		// TRANSLATORS Name of possible authenticator method. This signalize that the signer could be identified by email
		$this->setFriendlyName($this->identifyService->getL10n()->t('Email token'));
		parent::__construct(
			$identifyService,
		);
	}

	public function validateToSign(): void {
		$this->throwIfInvalidToken();
	}

	public function toArray(): array {
		$entity = $this->getEntity();

		$email = match ($entity->getIdentifierKey()) {
			'email' => $entity->getIdentifierValue(),
			'account' => $this->identifyService->getUserManager()->get($entity->getIdentifierValue())
				?->getEMailAddress(),
			default => null,
		};

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [['message' => $this->identifyService->getL10n()->t('Invalid email')]],
			]));
		}

		$emailLowercase = strtolower($email);

		$code = $entity->getCode();
		$identifiedAt = $entity->getIdentifiedAtDate();
		$codeSentByUser = $this->codeSentByUser;

		$hasConfirmCode = !empty($code);
		$needCode = empty($code)
			|| empty($identifiedAt)
			|| empty($codeSentByUser);

		$return = parent::toArray();
		$return['identifyMethod'] = $entity->getIdentifierKey();
		$return['needCode'] = $needCode;
		$return['hasConfirmCode'] = $hasConfirmCode;
		$return['blurredEmail'] = $this->blurEmail($emailLowercase);
		$return['hashOfEmail'] = md5($emailLowercase);
		return $return;
	}

	private function blurEmail(string $email): string {
		$blur = new Blur($email);
		return $blur->make();
	}

	public function requestCode(string $identify, string $method): void {
		$signRequestMapper = $this->identifyService->getSignRequestMapper();
		$signRequest = $signRequestMapper->getById($this->getEntity()->getSignRequestId());
		$displayName = $signRequest->getDisplayName();
		if ($identify === $displayName) {
			$displayName = '';
		}
		$code = $this->tokenService->sendCodeByEmail($identify, $displayName);
		$this->getEntity()->setCode($code);
		$this->identifyService->save($this->getEntity());
	}
}
