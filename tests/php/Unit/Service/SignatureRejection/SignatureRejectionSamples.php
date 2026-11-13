<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureRejection;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyConfig;

trait SignatureRejectionSamples {
	private const REJECTED_AT = '2026-09-06T10:00:00+00:00';

	private function file(int $status = FileStatus::ABLE_TO_SIGN->value, ?int $parentFileId = null): File {
		$file = new File();
		$file->setId(10);
		$file->setUserId('requester');
		$file->setUuid('doc-uuid');
		$file->setStatus($status);
		if ($parentFileId !== null) {
			$file->setParentFileId($parentFileId);
		}
		return $file;
	}

	private function envelope(int $status = FileStatus::ABLE_TO_SIGN->value): File {
		$envelope = $this->file($status);
		$envelope->setId(1);
		$envelope->setUuid('envelope-uuid');
		$envelope->setNodeType('envelope');
		return $envelope;
	}

	private function signer(int $status = SignRequestStatus::ABLE_TO_SIGN->value, int $id = 1): SignRequest {
		$signer = new SignRequest();
		$signer->setId($id);
		$signer->setFileId(10);
		$signer->setDisplayName('Signer');
		$signer->setStatus($status);
		return $signer;
	}

	private static function policy(string $behavior = 'continue', string $commentMode = 'disabled'): SignatureRejectionPolicyConfig {
		return SignatureRejectionPolicyConfig::fromValues(enabled: true, behavior: $behavior, commentMode: $commentMode);
	}
}
