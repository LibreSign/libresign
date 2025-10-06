<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod\SignatureMethod;

use OCA\Libresign\Service\IdentifyMethod\IdentifyService;

class TwofactorGatewayToken extends AbstractSignatureMethod implements IToken {
	private const VISIBILITY_START = 2;
	private const VISIBILITY_END = 2;

	public function __construct(
		protected IdentifyService $identifyService,
		protected TokenService $tokenService,
	) {
		parent::__construct(
			$identifyService,
		);
	}

	#[\Override]
	public function validateToSign(): void {
		$this->throwIfInvalidToken();
	}

	#[\Override]
	public function toArray(): array {
		$entity = $this->getEntity();

		$identifier = match ($entity->getIdentifierKey()) {
			'identifier' => $entity->getIdentifierValue(),
			'account' => $this->identifyService->getUserManager()->get($entity->getIdentifierValue())
				?->getEMailAddress() ?? '',
			default => '',
		};

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
		$return['blurredIdentifier'] = $this->blurIdentifier($identifier);
		$return['hashOfIdentifier'] = md5($identifier);
		return $return;
	}

	private function blurIdentifier(
		string $identifier,
		int $visibleStart = self::VISIBILITY_START,
		int $visibleEnd = self::VISIBILITY_END,
	): string {
		$length = mb_strlen($identifier);

		if ($length <= $visibleStart + $visibleEnd) {
			return str_repeat('*', $length);
		}

		$start = mb_substr($identifier, 0, $visibleStart);
		$end = mb_substr($identifier, -$visibleEnd);

		$maskedLength = $length - ($visibleStart + $visibleEnd);

		return $start . str_repeat('*', $maskedLength) . $end;
	}

	#[\Override]
	public function requestCode(string $identifier, string $method): void {
		$signRequestMapper = $this->identifyService->getSignRequestMapper();
		$signRequest = $signRequestMapper->getById($this->getEntity()->getSignRequestId());
		$displayName = $signRequest->getDisplayName();
		if ($identifier === $displayName) {
			$displayName = '';
		}
		if ($method === 'email') {
			$code = $this->tokenService->sendCodeByEmail($identifier, $displayName);
		} else {
			$code = $this->tokenService->sendCodeByGateway($identifier, $method);
		}
		$this->getEntity()->setCode($code);
		$this->identifyService->save($this->getEntity());
	}
}
