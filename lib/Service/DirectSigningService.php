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
use OCP\Files\File;
use OCP\IUser;

/**
 * Thin orchestration of the existing request and signing lifecycle.
 *
 * This does not define a new kind of signature or persist separate direct-sign
 * records. All state belongs to the ordinary LibreSign File and SignRequest.
 */
class DirectSigningService {
	public function __construct(
		private RequestSignatureWorkflowService $requestWorkflow,
		private SignFileService $signFileService,
		private SignerValidator $signerValidator,
		private IdentifyMethodService $identifyMethodService,
		private SigningPreparationService $signingPreparationService,
		private FileMapper $fileMapper,
		private FolderService $folderService,
		private PolicyService $policyService,
	) {
	}

	/**
	 * Create and sign a single PDF on behalf of the authenticated account.
	 * The request persists in the normal LibreSign document history.
	 *
	 * @param array<string, mixed> $file
	 * @param array<string, mixed> $settings
	 * @param array<string, mixed>|null $policy
	 * @return array{content: string, filename: string, uuid: string}
	 */
	public function sign(
		IUser $user,
		array $file,
		string $name,
		string $method = 'clickToSign',
		string $token = '',
		array $settings = [],
		?array $policy = null,
	): array {
		$this->validateInput($file, $name, $method);
		$this->assertAccountSigningEnabled($user, $method);

		// Do not introduce another signing lifecycle. Normal request creation
		// enforces the current actor's permissions, policy and file validation.
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

		$signRequest = $this->signFileService->getSignRequestToSign($createdFile, null, $user);
		// Interactive signing performs this in the signer middleware; the new
		// request does not exist yet when the direct HTTP request is received.
		$this->signerValidator->validateSigner($signRequest->getUuid(), $user);
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

		return $this->readSignedPdf((int)$createdFile->getId(), $user);
	}

	/** @param array<string, mixed> $file */
	private function validateInput(array $file, string $name, string $method): void {
		if (trim($name) === '' || !str_ends_with(strtolower($name), '.pdf')) {
			throw new LibresignException('A PDF filename is required.', Http::STATUS_BAD_REQUEST);
		}
		if (!in_array($method, ['clickToSign', 'password'], true)) {
			throw new LibresignException('An allowed account signature method is required.', Http::STATUS_BAD_REQUEST);
		}
		$inputs = 0;
		foreach (['base64', 'nodeId', 'path'] as $key) {
			$inputs += isset($file[$key]) && $file[$key] !== '' ? 1 : 0;
		}
		// Do not fetch external URLs or accept envelopes in a single-PDF
		// command. The workflow will validate the selected file input.
		if ($inputs !== 1 || array_diff(array_keys($file), ['base64', 'nodeId', 'path']) !== []) {
			throw new LibresignException('Provide exactly one PDF as file.base64, file.nodeId, or file.path.', Http::STATUS_BAD_REQUEST);
		}
	}

	private function assertAccountSigningEnabled(IUser $user, string $method): void {
		$resolved = $this->policyService->resolveForUser(IdentifyMethodsPolicy::KEY, $user);
		$normalized = IdentifyMethodsPolicyValue::normalize($resolved->getEffectiveValue());
		foreach (IdentifyMethodsPolicyValue::extractFactors($normalized) as $factor) {
			if (($factor['name'] ?? null) !== IdentifyMethodService::IDENTIFY_ACCOUNT) {
				continue;
			}
			$methods = $factor['signatureMethods'] ?? [];
			if (($factor['enabled'] ?? false) && is_array($methods) && ($methods[$method]['enabled'] ?? false) === true) {
				return;
			}
		}
		throw new LibresignException('Account signing is not allowed by the effective identification policy.', Http::STATUS_FORBIDDEN);
	}

	/** @return array{content: string, filename: string, uuid: string} */
	private function readSignedPdf(int $fileId, IUser $user): array {
		$file = $this->fileMapper->getById($fileId);
		if (!$file instanceof LibreSignFile
			|| $file->getUserId() !== $user->getUID()
			|| $file->getStatus() !== FileStatus::SIGNED->value
			|| !$file->getSignedHash()
			|| !$file->getSignedNodeId()) {
			throw new LibresignException('The signing request has not completed.', Http::STATUS_CONFLICT);
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
