<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\External;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Service\Signing\SyntheticCscSigningOperation;

/** Assemble a validated publication from server-owned state and an immutable PDF. */
final class PublicationPreparer implements PublicationPreparation {
	public function __construct(
		private SessionStore $sessions,
		private SyntheticCscSigningOperation $signing,
		private FileMapper $files,
		private DocumentSource $documents,
		private ImmutableArtifactStore $artifacts,
	) {
	}

	/**
	 * Reconcile a previously submitted signature without calling signHash.
	 * Authorization and certificate discovery must be performed by the caller.
	 */
	public function prepare(
		AuthorizedRequest $authorized,
		string $sessionId,
		#[\SensitiveParameter] string $expectedCertificatePem,
	): PublicationContext {
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
		return $context;
	}
}
