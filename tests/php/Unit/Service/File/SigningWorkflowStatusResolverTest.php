<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use DateTime;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\IdDocs;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\File\SigningWorkflowStatusResolver;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionPolicyService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SigningWorkflowStatusResolverTest extends TestCase {
	private ?IdDocs $idDocs = null;

	/** @param array{enabled: bool, cancel_workflow: bool} $policy */
	private function resolver(array $policy): SigningWorkflowStatusResolver {
		$idDocsMapper = $this->createMock(IdDocsMapper::class);
		$idDocsMapper->method('getByFileId')->willReturnCallback(function (): IdDocs {
			if ($this->idDocs === null) {
				throw new DoesNotExistException('no identification document');
			}
			return $this->idDocs;
		});
		$policyService = $this->createMock(SignatureRejectionPolicyService::class);
		$policyService->method('getPolicyValue')->willReturn($policy);
		return new SigningWorkflowStatusResolver($idDocsMapper, $policyService);
	}

	private static function file(): FileEntity {
		$file = new FileEntity();
		$file->setId(42);
		return $file;
	}

	/** @return list<SignRequest> */
	private static function signers(int $signed, int $rejected, int $pending): array {
		$signers = [];
		for ($index = 1; $index <= $signed + $rejected + $pending; $index++) {
			$signer = new SignRequest();
			$signer->setId($index);
			if ($index <= $signed) {
				$signer->setSigned(new DateTime());
				$signer->setStatusEnum(SignRequestStatus::SIGNED);
			} elseif ($index <= $signed + $rejected) {
				$signer->setStatusEnum(SignRequestStatus::REJECTED);
			} else {
				$signer->setStatusEnum(SignRequestStatus::ABLE_TO_SIGN);
			}
			$signers[] = $signer;
		}
		return $signers;
	}

	/** @param array{enabled: bool, cancel_workflow: bool} $policy */
	#[DataProvider('completionCases')]
	public function testCompletion(array $policy, int $signed, int $rejected, int $pending, ?FileStatus $expected): void {
		$this->assertSame($expected, $this->resolver($policy)->resolve(self::file(), self::signers($signed, $rejected, $pending)));
	}

	public static function completionCases(): array {
		$continue = ['enabled' => true, 'cancel_workflow' => false];
		$cancel = ['enabled' => true, 'cancel_workflow' => true];
		$disabled = ['enabled' => false, 'cancel_workflow' => false];
		return [
			'no signers' => [$continue, 0, 0, 0, null],
			'all signed' => [$cancel, 2, 0, 0, FileStatus::SIGNED],
			'continue: signed then last rejected' => [$continue, 1, 1, 0, FileStatus::SIGNED],
			'continue: rejected then signed' => [$continue, 1, 1, 0, FileStatus::SIGNED],
			'continue: still pending' => [$continue, 1, 1, 1, FileStatus::PARTIAL_SIGNED],
			'continue: everyone rejected without signature' => [$continue, 0, 2, 0, null],
			'cancel policy: signed and rejected' => [$cancel, 1, 1, 0, FileStatus::PARTIAL_SIGNED],
			'disabled policy: signed and rejected' => [$disabled, 1, 1, 0, FileStatus::PARTIAL_SIGNED],
			'unsigned and pending' => [$continue, 0, 1, 1, null],
		];
	}

	public function testUnsignedIdentificationUploaderPlaceholderIsIgnored(): void {
		$doc = new IdDocs();
		$doc->setSignRequestId(3);
		$this->idDocs = $doc;
		$signers = self::signers(1, 0, 0);
		$placeholder = new SignRequest();
		$placeholder->setId(3);
		$placeholder->setStatusEnum(SignRequestStatus::DRAFT);
		$signers[] = $placeholder;
		$this->assertSame(FileStatus::SIGNED, $this->resolver(['enabled' => false, 'cancel_workflow' => false])->resolve(self::file(), $signers));
	}
}
