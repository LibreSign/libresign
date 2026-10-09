<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\Signature\FilePolicy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Policy\AbstractFilePolicyApplier;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use OCP\AppFramework\Http;
use OCP\IUser;

class SignatureFlowFilePolicyApplier extends AbstractFilePolicyApplier {

	#[\Override]
	public function apply(FileEntity $file, array $data): void {
		$user = ($data['userManager'] ?? null) instanceof IUser ? $data['userManager'] : null;
		$requestOverrides = $this->getOverrides($data);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUser(SignatureFlowPolicy::KEY, $user, $requestOverrides)
			: $this->policyService->resolveForUser(SignatureFlowPolicy::KEY, $user, $requestOverrides, $activeContext);
		$this->assertOverrideAllowed($requestOverrides, $resolvedPolicy);
		$file->setSignatureFlowEnum(SignatureFlow::from((string)$resolvedPolicy->getEffectiveValue()));
		$this->storePolicySnapshot($file, $resolvedPolicy);
	}

	#[\Override]
	public function sync(FileEntity $file, array $data): void {
		$requestOverrides = $this->getOverrides($data);
		if ($this->isPolicySnapshotFrozen($file, SignatureFlowPolicy::KEY)) {
			$this->assertFrozenFlowIsKept($file, $requestOverrides);
			return;
		}

		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUserId(SignatureFlowPolicy::KEY, $file->getUserId(), $requestOverrides)
			: $this->policyService->resolveForUserId(SignatureFlowPolicy::KEY, $file->getUserId(), $requestOverrides, $activeContext);
		$this->assertOverrideAllowed($requestOverrides, $resolvedPolicy);
		$newFlow = SignatureFlow::from((string)$resolvedPolicy->getEffectiveValue());
		$metadataBeforeUpdate = $file->getMetadata() ?? [];
		$this->storePolicySnapshot($file, $resolvedPolicy);
		$metadataChanged = ($file->getMetadata() ?? []) !== $metadataBeforeUpdate;

		if ($file->getSignatureFlowEnum() !== $newFlow || $metadataChanged) {
			$file->setSignatureFlowEnum($newFlow);
			$this->fileService->update($file);
		}
	}

	#[\Override]
	public function supportsCoreFlowSync(): bool {
		return true;
	}

	/** @return array<string, string> */
	private function getOverrides(array $data): array {
		return $this->extractSinglePolicyOverride($data, SignatureFlowPolicy::KEY);
	}

	/** @param array<string, string> $requestOverrides */
	private function assertOverrideAllowed(array $requestOverrides, ResolvedPolicy $resolvedPolicy): void {
		$this->assertRequestOverrideAllowed($requestOverrides, $resolvedPolicy, 'Signature flow override is blocked by %s.');
	}

	/**
	 * A frozen request keeps the flow it was sent with. A client may still send
	 * that same flow along an unrelated update, which changes nothing.
	 *
	 * @param array<string, string> $requestOverrides
	 */
	private function assertFrozenFlowIsKept(FileEntity $file, array $requestOverrides): void {
		if ($requestOverrides === [] || $requestOverrides[SignatureFlowPolicy::KEY] === $file->getSignatureFlowEnum()->value) {
			return;
		}

		throw new LibresignException(
			// TRANSLATORS Error shown when someone tries to change whether signers sign in order after the signing flow already started.
			$this->l10n?->t('The signing order cannot be changed after the signing flow has started.')
				?? 'The signing order cannot be changed after the signing flow has started.',
			Http::STATUS_UNPROCESSABLE_ENTITY,
		);
	}
}
