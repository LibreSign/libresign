<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureRejection;

use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\SignatureRejectionVisibility;

/**
 * Who is reading the signers of one file, as far as rejection visibility is
 * concerned.
 *
 * The requester sees every rejection, and a signer always sees their own.
 * Anybody else sees what the `rejection_visibility` level allows for the
 * narrowest audience they belong to: a signer taking part in the workflow is a
 * participant; an observer, another authenticated user and an anonymous
 * reader are the public, since they could only reach the file through the
 * validation page.
 */
final class RejectionViewer {
	/** @var list<int> */
	private readonly array $ownSignRequestIds;
	private readonly bool $participant;

	/**
	 * @param SignRequest[] $ownSignRequests the sign requests of the file that belong to the viewer
	 */
	public function __construct(
		private readonly bool $requester,
		array $ownSignRequests,
	) {
		$this->ownSignRequestIds = array_values(array_map(
			static fn (SignRequest $signRequest): int => $signRequest->getId(),
			$ownSignRequests,
		));
		$this->participant = array_filter(
			$ownSignRequests,
			static fn (SignRequest $signRequest): bool => $signRequest->getParticipantRoleEnum()->canSign(),
		) !== [];
	}

	/** Whether the viewer may see everything about this signer's rejection. */
	public function isPrivilegedFor(SignRequest $signRequest): bool {
		return $this->requester || in_array($signRequest->getId(), $this->ownSignRequestIds, true);
	}

	/** The narrowest audience the viewer belongs to. */
	public function getAudience(): SignatureRejectionVisibility {
		return match (true) {
			$this->requester => SignatureRejectionVisibility::REQUESTER,
			$this->participant => SignatureRejectionVisibility::PARTICIPANTS,
			default => SignatureRejectionVisibility::PUBLIC,
		};
	}
}
