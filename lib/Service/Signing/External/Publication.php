<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\External;

/**
 * Publish an already validated CSC signature with a single database commit.
 *
 * No signing operation is submitted here. Failed database work leaves the
 * immutable document available for safe recovery, while notifications run
 * only after the transaction commits.
 */
final class Publication {
	public function __construct(
		private PublicationPreparation $preparer,
		private PublicationTransaction $committer,
		private PublicationNotifications $events,
	) {
	}

	/** @return array{status:string,sessionId:string,signedNodeId:int,signedHash:string} */
	public function publish(
		AuthorizedRequest $authorized,
		string $sessionId,
		#[\SensitiveParameter] string $expectedCertificatePem,
	): array {
		$context = $this->preparer->prepare($authorized, $sessionId, $expectedCertificatePem);
		$committed = $this->committer->commit($context);
		$this->events->dispatch(
			$committed['file'], $authorized->signRequest, $context->output,
			$committed['previousStatus'], $committed['activated'],
		);
		return [
			'status' => 'completed',
			'sessionId' => $sessionId,
			'signedNodeId' => $context->output->getId(),
			'signedHash' => $context->signedHash,
		];
	}
}
