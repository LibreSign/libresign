<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureRejection;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\SignatureRejectionCommentMode;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValue;
use OCA\Libresign\Service\Policy\ResolvesFrozenFilePolicySnapshot;

/**
 * Reads the rejection rules that a signature request was created with.
 *
 * The value frozen on the request is the only source of truth for the signing
 * flow: the live policy is never consulted here, so a later policy change cannot
 * alter an existing request, and a request that never opted in keeps rejection
 * disabled no matter what the policy allows today.
 *
 * @psalm-type SignatureRejectionPolicyShape = array{
 *     enabled: bool,
 *     comment_mode: string,
 *     cancel_workflow: bool,
 *     public_status: bool,
 *     show_comment_on_validation: bool,
 * }
 */
class SignatureRejectionPolicyService {
	use ResolvesFrozenFilePolicySnapshot;

	public function __construct(
		private FileMapper $fileMapper,
	) {
	}

	/**
	 * @return SignatureRejectionPolicyShape
	 */
	public function getPolicyValue(?FileEntity $file = null): array {
		return $this->findSnapshot($file) ?? SignatureRejectionPolicyValue::defaults();
	}

	public function isEnabled(?FileEntity $file = null): bool {
		return $this->getPolicyValue($file)['enabled'];
	}

	public function getCommentMode(?FileEntity $file = null): SignatureRejectionCommentMode {
		return SignatureRejectionCommentMode::from($this->getPolicyValue($file)['comment_mode']);
	}

	public function cancelsWorkflow(?FileEntity $file = null): bool {
		return $this->getPolicyValue($file)['cancel_workflow'];
	}

	protected function getFrozenPolicyKey(): string {
		return SignatureRejectionPolicy::KEY;
	}

	/**
	 * @return SignatureRejectionPolicyShape|null
	 */
	protected function normalizeFrozenPolicyEffectiveValue(mixed $value): ?array {
		return SignatureRejectionPolicyValue::normalize($value);
	}
}
