<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Dashboard\Resolver;

use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\Dashboard\ValueObject\DashboardParticipantContext;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\IUser;

final class DashboardParticipantResolver
{

	/**
	 * @param SignRequest[] $signRequests
	 * @param array<int, array<string, IdentifyMethod>> $identifyMethods
	 */
	public function resolve(
		array $signRequests,
		array $identifyMethods,
		IUser $user,
	): DashboardParticipantContext {

		foreach ($signRequests as $signRequest) {

			$methods =
				$identifyMethods[$signRequest->getId()] ?? [];

			foreach ($methods as $method) {

				$isAccountMatch =
					$method->getIdentifierKey()
					=== IdentifyMethodService::IDENTIFY_ACCOUNT
					&&
					$method->getIdentifierValue()
					=== $user->getUID();

				$isEmailMatch =
					$method->getIdentifierKey()
					=== IdentifyMethodService::IDENTIFY_EMAIL
					&&
					$method->getIdentifierValue()
					=== $user->getEMailAddress();

				$isMatchingParticipant =
					$isAccountMatch
					|| $isEmailMatch;

				if (!$isMatchingParticipant) {
					continue;
				}

				$status = $signRequest->getStatusEnum();

				return new DashboardParticipantContext(
					signRequest: $signRequest,
					isSigner: true,
					canSignNow: $status === SignRequestStatus::ABLE_TO_SIGN,
					hasSigned: $status === SignRequestStatus::SIGNED,
					isBlockedBySequence: $status === SignRequestStatus::DRAFT,
					signingOrder: $signRequest->getSigningOrder(),
					displayName: $signRequest->getDisplayName(),
				);
			}
		}

		return new DashboardParticipantContext(
			signRequest: null,
			isSigner: false,
			canSignNow: false,
			hasSigned: false,
			isBlockedBySequence: false,
			signingOrder: null,
			displayName: null,
		);
	}
}
