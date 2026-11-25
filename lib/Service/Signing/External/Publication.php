<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\External;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Service\Signing\SyntheticCscSigningOperation;

/**
 * Reconcile the synthetic CSC PDF with the actual LibreSign workflow.
 *
 * A signed artifact is immutable and identified by session. An interrupted
 * publication may leave that artifact in the owner's folder; its exact hash
 * is checked before reuse. Older signed revisions are never overwritten.
 *
 * The SignRequest state, file pointer/status, next signer activation and
 * durable CSC COMPLETED transition share a database transaction. Event
 * dispatch occurs only after commit, following LibreSign's existing best-
 * effort delivery model (which does not promise exactly-once webhooks).
 *
 * The caller MUST obtain AuthorizedRequest immediately before
 * calling this service, and the certificate PEM MUST come from trusted CSC
 * discovery, never a browser-supplied parameter. No private key is used.
 */
final class Publication {
	public function __construct(
		private SessionStore $sessions,
		private SyntheticCscSigningOperation $signing,
		private FileMapper $files,
		private DocumentSource $documents,
		private ImmutableArtifactStore $artifacts,
		private PublicationCommitter $committer,
		private PublicationEvents $events,
	) {
	}

	/**
	 * Reconcile one already-validated CSC signature without submitting signHash.
	 * A trusted caller must recheck current signer authorization first.
	 *
	 * @return array{status:string,sessionId:string,signedNodeId:int,signedHash:string}
	 */
	public function publish(
		AuthorizedRequest $authorized,
		string $sessionId,
		string $expectedCertificatePem,
	): array {
		$request = $authorized->signRequest;
		$requestId = $request->getId();
		$fileId = $authorized->file->getId();
		if ($requestId === null || $requestId < 1 || $fileId === null || $fileId < 1
			|| $request->getFileId() !== $fileId) {
			throw new \DomainException('CSC signing request context is invalid');
		}
		$session = $this->sessions->getForSignRequest($sessionId, $requestId, $authorized->actorId);
		if ($session['status'] !== SessionState::COMPLETING->value) {
			throw new \DomainException('CSC signature has not reserved publication');
		}

		// Resolve the source exclusively from current LibreSign storage. Never
		// accept a source PDF, document revision or resulting PDF from the UI.
		$file = $this->files->getById($fileId);
		$document = $this->documents->get($file);
		$validatedPdf = $this->signing->reconstructForReconciliation(
			$sessionId, $requestId, $authorized->actorId,
			$document->revision, $document->pdf, $expectedCertificatePem,
		);
		$hash = hash('sha256', $validatedPdf);
		$output = $this->artifacts->ensure($document->node, $sessionId, $fileId, $requestId, $validatedPdf, $hash);
		$document->assertUnchanged();

		$context = new PublicationContext(
			$authorized, (int)$session['revision'], $file, $document,
			$output, $hash, $expectedCertificatePem, $sessionId,
		);
		$committed = $this->committer->commit($context);
		$this->events->dispatch(
			$committed['file'], $request, $output, $committed['previousStatus'], $committed['activated'],
		);
		return [
			'status' => 'completed',
			'sessionId' => $sessionId,
			'signedNodeId' => $output->getId(),
			'signedHash' => $hash,
		];
	}


}
