<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Db\File as LibreSignFile;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscIntegration;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\PdfContentDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureActorDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureMetadataDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureProfile;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SigningCertificateDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SigningContextDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SigningOptionsDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\RemoteSigning\DTO\RemoteCertificate;
use OCP\Files\File as NextcloudFile;

/**
 * Provider-neutral execution surface for the explicitly pinned synthetic
 * CSC v1 laboratory. Production CSC personal signing is not exposed here.
 *
 * All actor/request/revision/certificate/PDF data originates from trusted
 * LibreSign storage or its pinned laboratory factory, never from a client.
 * A fresh authorization check precedes remote discovery, start, continuation
 * or status lookup. A session ID is not an access token.
 */
final class ExternalSigningWorkflowService {
	private const MAX_INPUT_BYTES = 25 * 1024 * 1024;

	public function __construct(
		private ExternalSigningAuthorizationService $authorization,
		private ExternalSigningLaboratoryFactory $laboratories,
		private ExternalSigningCredentialSelectionService $selection,
		private ExternalSigningSessionStore $sessions,
		private SyntheticCscSigningOperation $crypto,
		private ExternalSigningPublicationService $publisher,
		private FileMapper $files,
		private FolderService $folders,
		private ExternalSigningIntegrationScopeAccess $scopeAccess,
	) {
	}

	/**
	 * Discover allowed synthetic signing methods for one authorized signer;
	 * never enumerate every remote credential merely from a guest URL.
	 *
	 * @return array<string, mixed>
	 */
	public function availability(
		string $signRequestUuid,
		string $method,
		string $identity,
		#[\SensitiveParameter] string $token,
		string $integrationId = '',
	): array {
		$actor = $this->authorization->authorize($signRequestUuid, $method, $identity, $token);
		$session = $this->sessions->findForSignRequest($actor->signRequest->getId(), $actor->actorId);
		if ($integrationId === '') {
			return [
				'integrations' => $this->laboratories->listEligible(fn (ExternalIntegrationRecord $record): bool => $this->canUseIntegration($record, $actor, $method, $identity)),
				'integration' => null,
				'credentials' => [],
				'syntheticOnly' => true,
				'personalSignerAuthorization' => false,
				'session' => $session,
			];
		}
		$lab = $this->laboratories->resolve($integrationId);
		$this->assertCanUseIntegration($lab['record'], $actor, $method, $identity);
		$selection = $this->selection->discoverSynthetic($signRequestUuid, $method, $identity,
			$token, $integrationId, $lab['laboratory']);
		$selection['integrations'] = [$selection['integration']];
		$selection['session'] = $session;
		return $selection;
	}

	/**
	 * Prepare an eligible pinned credential and persist the exact server-owned
	 * source revision; never accept prepared state or X.509 bytes from a UI.
	 *
	 * @return array<string, int|string>
	 */
	public function start(
		string $signRequestUuid,
		string $method,
		string $identity,
		#[\SensitiveParameter] string $token,
		string $integrationId,
		string $credentialId,
		string $attemptId,
		string $profile,
	): array {
		$actor = $this->authorization->authorize($signRequestUuid, $method, $identity, $token);
		$lab = $this->laboratories->resolve($integrationId);
		$this->assertCanUseIntegration($lab['record'], $actor, $method, $identity);
		$selection = $this->selection->discoverSynthetic($signRequestUuid, $method, $identity,
			$token, $integrationId, $lab['laboratory']);
		$chosen = null;
		foreach ($selection['credentials'] as $item) {
			if ($item['id'] === $credentialId) {
				$chosen = $item;
				break;
			}
		}
		if ($chosen === null || !in_array($profile, $chosen['profiles'], true)) {
			throw new \DomainException('Selected CSC credential or PDF profile is not eligible');
		}
		$certificate = $this->certificate($lab['laboratory'], $credentialId,
			(string)$chosen['certificateSha256']);
		$source = $this->source($actor->file);
		$signerName = trim((string)$actor->signRequest->getDisplayName());
		if ($signerName === '') {
			$signerName = 'Synthetic CSC signer';
		}
		$context = new SigningContextDto(
			new PdfContentDto($source['pdf']),
			new SigningOptionsDto(
				metadata: new SignatureMetadataDto(actor: new SignatureActorDto(name: mb_substr($signerName, 0, 128))),
				signatureProfile: match ($profile) {
					'pdf-basic' => SignatureProfile::PdfBasic,
					'pades-baseline-b' => SignatureProfile::PadesBaselineB,
					default => throw new \DomainException('Unsupported external signing PDF profile'),
				},
				useDefaultAppearance: false,
			),
			new SigningCertificateDto($certificate->certificate->certificatePem),
		);
		// The signing helper and the persisted session both independently
		// check certificate, integration revision and exact PDF input.
		return $this->crypto->prepare($lab['laboratory'], $lab['record'], $credentialId, $context,
			$actor->actorId, $actor->signRequest->getId(), $attemptId, $source['revision'],
			new \DateTimeImmutable('+15 minutes'));
	}

	/**
	 * Continue one authorized operation. A SIGNING_PENDING or UNCERTAIN
	 * submission is reported without ever calling the private key again.
	 * A COMPLETING session can only reconcile stored validated bytes and
	 * the native SignRequest publication, not reserve a new operation.
	 *
	 * @return array<string, mixed>
	 */
	public function continue(
		string $signRequestUuid,
		string $method,
		string $identity,
		#[\SensitiveParameter] string $token,
		string $sessionId,
	): array {
		$actor = $this->authorization->authorize($signRequestUuid, $method, $identity, $token);
		$session = $this->sessions->getForSignRequest($sessionId, $actor->signRequest->getId(), $actor->actorId);
		if (in_array($session['status'], [
			ExternalSigningSessionState::SIGNING_PENDING->value,
			ExternalSigningSessionState::UNCERTAIN->value,
			ExternalSigningSessionState::REJECTED->value,
			ExternalSigningSessionState::EXPIRED->value,
			ExternalSigningSessionState::FAILED->value,
		], true)) {
			return ['session' => $session, 'retryAllowed' => false, 'publication' => null];
		}
		$lab = $this->laboratories->resolve((string)$session['integrationId']);
		$this->assertCanUseIntegration($lab['record'], $actor, $method, $identity);
		if ($lab['record']->revision !== (int)$session['integrationRevision']) {
			throw new \DomainException('External signing integration changed during signing');
		}
		$certificate = $this->certificate($lab['laboratory'], (string)$session['credentialId'],
			(string)$session['certificateSha256']);
		$source = $this->source($actor->file);
		$signaturePem = $certificate->certificate->certificatePem;
		if (in_array($session['status'], [
			ExternalSigningSessionState::PREPARING->value,
			ExternalSigningSessionState::READY_TO_SIGN->value,
		], true)) {
			try {
				$this->crypto->sign($lab['laboratory'], $lab['record'], $sessionId,
					$actor->signRequest->getId(), $actor->actorId, $source['revision'], $source['pdf']);
			} catch (\DomainException $e) {
				// Any operation whose remote result is indeterminate stays
				// non-retryable. Surface its stored state, not an HTTP retry.
				$fresh = $this->sessions->getForSignRequest($sessionId,
					$actor->signRequest->getId(), $actor->actorId);
				if (in_array($fresh['status'], ['uncertain', 'signing_pending'], true)) {
					return ['session' => $fresh, 'retryAllowed' => false, 'publication' => null];
				}
				throw $e;
			}
		} elseif ($session['status'] === ExternalSigningSessionState::SIGNATURE_RECEIVED->value) {
			$this->crypto->completeAndReserve($sessionId, $actor->signRequest->getId(),
				$actor->actorId, $source['revision'], $source['pdf'], $signaturePem);
		} elseif ($session['status'] !== ExternalSigningSessionState::COMPLETING->value) {
			throw new \DomainException('External signing session has no continuation operation');
		}
		// Revalidate the current signer identity/approval immediately before
		// making the visible File/SignRequest publication transition.
		$authorizedAgain = $this->authorization->authorize($signRequestUuid, $method, $identity, $token);
		$publication = $this->publisher->publish($authorizedAgain, $sessionId, $signaturePem);
		return ['session' => ['id' => $sessionId, 'status' => 'completed'],
			'retryAllowed' => false, 'publication' => $publication];
	}

	/** @return array<string, int|string>|null */
	public function status(
		string $signRequestUuid,
		string $method,
		string $identity,
		#[\SensitiveParameter] string $token,
	): ?array {
		$actor = $this->authorization->authorize($signRequestUuid, $method, $identity, $token);
		$session = $this->sessions->findForSignRequest($actor->signRequest->getId(), $actor->actorId);
		if ($session !== null) {
			$lab = $this->laboratories->resolve((string)$session['integrationId']);
			$this->assertCanUseIntegration($lab['record'], $actor, $method, $identity);
		}
		return $session;
	}

	private function canUseIntegration(
		ExternalIntegrationRecord $record,
		AuthorizedExternalSigningRequest $actor,
		string $method,
		string $identity,
	): bool {
		$signerUid = $method === IdentifyMethodService::IDENTIFY_ACCOUNT ? $identity : null;
		return $this->scopeAccess->canUse($record, $actor->file->getUserId(), $signerUid);
	}

	private function assertCanUseIntegration(
		ExternalIntegrationRecord $record,
		AuthorizedExternalSigningRequest $actor,
		string $method,
		string $identity,
	): void {
		if (!$this->canUseIntegration($record, $actor, $method, $identity)) {
			throw new \DomainException('External signing integration is not permitted for this policy scope');
		}
	}

	/**
	 * @return array{pdf:string, revision:string}
	 */
	private function source(LibreSignFile $file): array {
		$sourceNodeId = $file->getSignedNodeId() ?: $file->getNodeId();
		if ($sourceNodeId === null || $sourceNodeId < 1) {
			throw new \DomainException('Authoritative source PDF is unavailable');
		}
		$uid = $this->files->getStorageUserIdByUuid($file->getUuid());
		$node = $this->folders->getReadableNodeById($uid, $sourceNodeId);
		if (!$node instanceof NextcloudFile) {
			throw new \DomainException('Authoritative source document cannot be read');
		}
		$pdf = $node->getContent();
		$revision = $node->getEtag();
		if ($revision === '' || strlen($revision) > 128 || strlen($pdf) > self::MAX_INPUT_BYTES
			|| !str_starts_with($pdf, '%PDF-')) {
			throw new \DomainException('Authoritative PDF source or revision is invalid');
		}
		return ['pdf' => $pdf, 'revision' => $revision];
	}

	private function certificate(CscIntegration $lab, string $credentialId, string $fingerprint): RemoteCertificate {
		$matches = $lab->certificates($credentialId);
		if (count($matches) !== 1 || !hash_equals($credentialId, $matches[0]->identifier)) {
			throw new \DomainException('CSC signing credential is not available for this request');
		}
		$actual = @openssl_x509_fingerprint($matches[0]->certificate->certificatePem, 'sha256');
		if (!is_string($actual) || !hash_equals($fingerprint, strtolower($actual))) {
			throw new \DomainException('CSC signing credential certificate has changed');
		}
		return $matches[0];
	}
}
