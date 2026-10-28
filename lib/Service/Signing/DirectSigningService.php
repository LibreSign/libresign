<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicy;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicyValue;
use OCA\Libresign\Service\RequestSignatureWorkflowService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\Validation\SignerValidator;
use OCP\AppFramework\Http;
use OCP\IUser;

/**
 * Sign a new request synchronously using the existing LibreSign lifecycle.
 */
class DirectSigningService {
	public function __construct(
		private RequestSignatureWorkflowService $requestWorkflow,
		private SignFileService $signFileService,
		private SignerValidator $signerValidator,
		private IdentifyMethodService $identifyMethodService,
		private SigningPreparationService $signingPreparationService,
		private SignedPdfProvider $signedPdfProvider,
		private PolicyService $policyService,
	) {
	}

	/**
	 * Create a request, sign it as the authenticated account and return the PDF.
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
		$this->validateInput($file, $name, $method, $token);
		$this->assertAccountSigningEnabled($user, $method);

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

		return $this->signedPdfProvider->getSignedPdf((int)$createdFile->getId(), $user);
	}

	/** @param array<string, mixed> $file */
	private function validateInput(array $file, string $name, string $method, string $token): void {
		if (trim($name) === '' || !str_ends_with(strtolower($name), '.pdf')) {
			throw new LibresignException('A PDF filename is required.', Http::STATUS_BAD_REQUEST);
		}
		if (!in_array($method, ['clickToSign', 'password'], true)) {
			throw new LibresignException('An allowed account signature method is required.', Http::STATUS_BAD_REQUEST);
		}
		if ($method === 'password' && $token === '') {
			throw new LibresignException('The certificate password is required.', Http::STATUS_BAD_REQUEST);
		}
		$inputs = 0;
		foreach (['base64', 'nodeId', 'path'] as $key) {
			$inputs += isset($file[$key]) && $file[$key] !== '' ? 1 : 0;
		}
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
}
