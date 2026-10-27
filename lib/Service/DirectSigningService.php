<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File as LibreSignFile;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicy;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicyValue;
use OCA\Libresign\Service\Validation\SignerValidator;
use OCP\AppFramework\Http;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\IDBConnection;
use OCP\IUser;

class DirectSigningService {
	private const TABLE = 'libresign_direct_sign';

	public function __construct(
		private RequestSignatureWorkflowService $requestWorkflow,
		private SignFileService $signFileService,
		private SignerValidator $signerValidator,
		private IdentifyMethodService $identifyMethodService,
		private SigningPreparationService $signingPreparationService,
		private FileMapper $fileMapper,
		private FolderService $folderService,
		private PolicyService $policyService,
		private IDBConnection $db,
	) {
	}

	/**
	 * Prepare a single PDF request, sign as the authenticated Nextcloud account
	 * using the existing signing engine, and return the persisted signed PDF.
	 *
	 * On failure after creating the document the reservation remains, preventing
	 * a retry from silently generating a second signing request. A retry of a
	 * completed operation returns the same stored PDF.
	 *
	 * @param array<string, mixed> $file
	 * @param array<string, mixed> $settings
	 * @param array<string, mixed>|null $policy
	 * @return array{content: string, filename: string, uuid: string}
	 */
	public function sign(
		IUser $user,
		string $idempotencyKey,
		array $file,
		string $name,
		string $method,
		string $token = '',
		array $settings = [],
		?array $policy = null,
	): array {
		$this->validateInput($file, $name, $method, $idempotencyKey);
		$this->assertAccountSigningEnabled($user, $method);

		$fingerprint = hash('sha256', json_encode([
			$file, $name, $method, $token, $settings, $policy,
		], JSON_THROW_ON_ERROR));

		$reservation = $this->reserve($user->getUID(), $idempotencyKey, $fingerprint);
		if ($reservation['file_id'] !== null) {
			return $this->completedSignedPdf((int)$reservation['file_id'], $user);
		}
		if (!$reservation['new']) {
			throw new LibresignException(
				'The direct signing request is already in progress or requires investigation.',
				Http::STATUS_CONFLICT,
			);
		}

		$result = $this->requestWorkflow->createRequest(
			$user,
			$file,
			[],
			$name,
			$settings,
			[[
				'displayName' => $user->getDisplayName() ?: $user->getUID(),
				'participantRole' => 'signer',
				'identifyMethods' => [[
					'method' => IdentifyMethodService::IDENTIFY_ACCOUNT,
					'value' => $user->getUID(),
					'requirement' => 'optional',
				]],
				'notify' => 0,
			]],
			FileStatus::ABLE_TO_SIGN->value,
			null,
			$policy,
			[],
		);

		$createdFile = $result['file'];
		$this->recordFile($user->getUID(), $idempotencyKey, (int)$createdFile->getId());

		// Reuse exactly the same identity, geolocation, certificate and signing
		// checks as the two interactive signing endpoints.
		$signRequest = $this->signFileService->getSignRequestToSign($createdFile, null, $user);
		$this->signerValidator->validateSigner($signRequest->getUuid(), $user);
		// Request creation and identity discovery can cache distinct factor
		// instances. Reload before resolving the permitted signature method.
		$this->identifyMethodService->clearCache();

		$prepared = $this->signingPreparationService->prepare(
			$createdFile,
			$signRequest,
			$user,
			$method,
			'',
			$token,
		);
		$this->signFileService
			->setVisibleElements([])
			->storeUserMetadata($prepared['metadata'])
			->sign();

		return $this->completedSignedPdf((int)$createdFile->getId(), $user);
	}

	/**
	 * @param array<string, mixed> $file
	 */
	private function validateInput(array $file, string $name, string $method, string $idempotencyKey): void {
		if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._:-]{7,127}$/D', $idempotencyKey)) {
			throw new LibresignException('Idempotency-Key must contain 8 to 128 safe ASCII characters.', Http::STATUS_BAD_REQUEST);
		}
		if (trim($name) === '' || !str_ends_with(strtolower($name), '.pdf')) {
			throw new LibresignException('A PDF filename is required.', Http::STATUS_BAD_REQUEST);
		}
		if ($method === '' || !in_array($method, ['clickToSign', 'password'], true)) {
			throw new LibresignException('An allowed account signature method is required.', Http::STATUS_BAD_REQUEST);
		}
		$hasInput = 0;
		foreach (['base64', 'nodeId', 'path'] as $input) {
			$hasInput += isset($file[$input]) && $file[$input] !== '' ? 1 : 0;
		}
		// No remote URL fetching, envelopes, multiple document inputs, or
		// caller-supplied identities in this atomic, account-scoped command.
		if ($hasInput !== 1 || array_diff(array_keys($file), ['base64', 'nodeId', 'path']) !== []) {
			throw new LibresignException('Provide exactly one PDF as file.base64, file.nodeId, or file.path.', Http::STATUS_BAD_REQUEST);
		}
	}

	private function assertAccountSigningEnabled(IUser $user, string $method): void {
		$policy = $this->policyService->resolveForUser(IdentifyMethodsPolicy::KEY, $user);
		$normalized = IdentifyMethodsPolicyValue::normalize($policy->getEffectiveValue());
		foreach (IdentifyMethodsPolicyValue::extractFactors($normalized) as $factor) {
			if (($factor['name'] ?? null) !== IdentifyMethodService::IDENTIFY_ACCOUNT) {
				continue;
			}
			if (!($factor['enabled'] ?? false)) {
				break;
			}
			$methods = $factor['signatureMethods'] ?? [];
			if (!is_array($methods) || ($methods[$method]['enabled'] ?? false) !== true) {
				break;
			}
			return;
		}
		throw new LibresignException('Account signing is not allowed by the effective identification policy.', Http::STATUS_FORBIDDEN);
	}

	/**
	 * @return array{file_id: int|null, new: bool}
	 */
	private function reserve(string $userId, string $requestKey, string $fingerprint): array {
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->insert(self::TABLE)->values([
				'user_id' => $qb->createNamedParameter($userId),
				'request_key' => $qb->createNamedParameter($requestKey),
				'fingerprint' => $qb->createNamedParameter($fingerprint),
				'file_id' => $qb->createNamedParameter(null),
			]);
			$qb->executeStatement();
			return ['file_id' => null, 'new' => true];
		} catch (\Throwable $e) {
			// Do not swallow unrelated database errors: only an existing
			// reservation for this exact user/key represents a retry.
			$existing = $this->findReservation($userId, $requestKey);
			if ($existing === null) {
				throw $e;
			}
			if (!hash_equals((string)$existing['fingerprint'], $fingerprint)) {
				throw new LibresignException('Idempotency-Key was already used for a different request.', Http::STATUS_CONFLICT);
			}
			return [
				'file_id' => $existing['file_id'] !== null ? (int)$existing['file_id'] : null,
				'new' => false,
			];
		}
	}

	/** @return array<string, mixed>|null */
	private function findReservation(string $userId, string $requestKey): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('file_id', 'fingerprint')->from(self::TABLE)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('request_key', $qb->createNamedParameter($requestKey)));
		$result = $qb->executeQuery();
		try {
			$row = $result->fetchAssociative();
			return $row === false ? null : $row;
		} finally {
			$result->closeCursor();
		}
	}

	private function recordFile(string $userId, string $requestKey, int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('request_key', $qb->createNamedParameter($requestKey)));
		$qb->executeStatement();
	}

	/** @return array{content: string, filename: string, uuid: string} */
	private function completedSignedPdf(int $fileId, IUser $user): array {
		$file = $this->fileMapper->getById($fileId);
		if (!$file instanceof LibreSignFile
			|| $file->getUserId() !== $user->getUID()
			|| $file->getStatus() !== FileStatus::SIGNED->value
			|| !$file->getSignedHash()
			|| !$file->getSignedNodeId()) {
			throw new LibresignException(
				'The signing request has not completed. Retry only after investigating its status.',
				Http::STATUS_CONFLICT,
			);
		}
		$node = $this->folderService->getReadableNodeById($user->getUID(), $file->getSignedNodeId());
		if (!$node instanceof File || $node->getMimeType() !== 'application/pdf') {
			throw new LibresignException('The signed PDF is not accessible to this account.', Http::STATUS_FORBIDDEN);
		}

		$content = $node->getContent();
		if (!str_starts_with($content, '%PDF-')) {
			throw new LibresignException('The signed PDF could not be read.', Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return ['content' => $content, 'filename' => $node->getName(), 'uuid' => (string)$file->getUuid()];
	}
}
