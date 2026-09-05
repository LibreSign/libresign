<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureRejection;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Enum\SignatureRejectionCommentMode;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValue;
use OCP\IUser;

/**
 * Resolves the effective signature rejection rules of a document.
 *
 * A document keeps the rules that were in force when the signature request was
 * created through the policy snapshot stored in its metadata, so changing the
 * policy later never retroactively changes the rules of an ongoing workflow.
 *
 * @psalm-type SignatureRejectionPolicyShape = array{
 *     enabled: bool,
 *     comment_mode: string,
 *     allow_private_comment: bool,
 *     cancel_workflow: bool,
 *     public_status: bool,
 *     show_comment_on_validation: bool,
 * }
 */
class SignatureRejectionPolicyService {
	public function __construct(
		private PolicyService $policyService,
	) {
	}

	/**
	 * @return SignatureRejectionPolicyShape
	 */
	public function getPolicyValue(?FileEntity $file = null, ?IUser $user = null): array {
		$snapshotValue = $this->extractSnapshot($file?->getMetadata() ?? []);
		if ($snapshotValue !== null) {
			return $snapshotValue;
		}

		$resolved = $user instanceof IUser
			? $this->policyService->resolveForUser(SignatureRejectionPolicy::KEY, $user)
			: $this->policyService->resolveForUserId(SignatureRejectionPolicy::KEY, $file?->getUserId());

		return SignatureRejectionPolicyValue::normalize($resolved->getEffectiveValue());
	}

	/**
	 * Same as {@see self::getPolicyValue()} for callers that already loaded the
	 * file metadata but not the file entity.
	 *
	 * @param array<string, mixed> $fileMetadata
	 * @return SignatureRejectionPolicyShape
	 */
	public function getPolicyValueFromMetadata(array $fileMetadata, ?string $requesterUserId = null): array {
		$snapshotValue = $this->extractSnapshot($fileMetadata);
		if ($snapshotValue !== null) {
			return $snapshotValue;
		}

		return SignatureRejectionPolicyValue::normalize(
			$this->policyService->resolveForUserId(SignatureRejectionPolicy::KEY, $requesterUserId)->getEffectiveValue(),
		);
	}

	public function isEnabled(?FileEntity $file = null, ?IUser $user = null): bool {
		return $this->getPolicyValue($file, $user)['enabled'];
	}

	public function getCommentMode(?FileEntity $file = null, ?IUser $user = null): SignatureRejectionCommentMode {
		return SignatureRejectionCommentMode::from($this->getPolicyValue($file, $user)['comment_mode']);
	}

	public function cancelsWorkflow(?FileEntity $file = null, ?IUser $user = null): bool {
		return $this->getPolicyValue($file, $user)['cancel_workflow'];
	}

	/**
	 * @param array<string, mixed> $fileMetadata
	 * @return SignatureRejectionPolicyShape|null
	 */
	private function extractSnapshot(array $fileMetadata): ?array {
		$policySnapshot = $fileMetadata['policy_snapshot'] ?? null;
		if (!is_array($policySnapshot)) {
			return null;
		}

		$entry = $policySnapshot[SignatureRejectionPolicy::KEY] ?? null;
		if (!is_array($entry) || !array_key_exists('effectiveValue', $entry)) {
			return null;
		}

		return SignatureRejectionPolicyValue::normalize($entry['effectiveValue']);
	}
}
