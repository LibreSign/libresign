<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SignerRemovalRulesTest extends TestCase {
	#[DataProvider('fileStates')]
	public function testOnlyDraftAndUnsignedWorkflowAllowsSignerChanges(FileStatus $status, bool $allowed): void {
		$this->assertSame($allowed, $status->allowsSignerRemoval());
	}

	public static function fileStates(): array {
		return [
			'draft' => [FileStatus::DRAFT, true],
			'ready for signing' => [FileStatus::ABLE_TO_SIGN, true],
			'partially signed' => [FileStatus::PARTIAL_SIGNED, false],
			'completed' => [FileStatus::SIGNED, false],
			'signing in progress' => [FileStatus::SIGNING_IN_PROGRESS, false],
			'deleted' => [FileStatus::DELETED, false],
		];
	}

	#[DataProvider('signerStates')]
	public function testSigningEvidenceCannotBeMistakenForPendingRequest(
		SignRequestStatus $status,
		bool $hasTimestamp,
		bool $hasHash,
		bool $completed,
	): void {
		$request = new SignRequest();
		$request->setStatusEnum($status);
		if ($hasTimestamp) {
			$request->setSigned(new \DateTime('2026-01-01T00:00:00Z'));
		}
		if ($hasHash) {
			$request->setSignedHash('already-signed');
		}

		$this->assertSame($completed, $request->hasCompletedSigning());
	}

	public static function signerStates(): array {
		return [
			'draft' => [SignRequestStatus::DRAFT, false, false, false],
			'ready' => [SignRequestStatus::ABLE_TO_SIGN, false, false, false],
			'signed state' => [SignRequestStatus::SIGNED, false, false, true],
			'completion time persisted' => [SignRequestStatus::ABLE_TO_SIGN, true, false, true],
			'signed hash persisted' => [SignRequestStatus::ABLE_TO_SIGN, false, true, true],
			'both pieces of evidence' => [SignRequestStatus::SIGNED, true, true, true],
		];
	}
}
