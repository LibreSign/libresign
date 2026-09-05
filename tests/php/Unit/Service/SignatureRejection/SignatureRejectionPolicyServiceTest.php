<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureRejection;

use OCA\Libresign\Db\File;
use OCA\Libresign\Enum\SignatureRejectionCommentMode;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValue;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionPolicyService;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignatureRejectionPolicyServiceTest extends TestCase {
	private PolicyService&MockObject $policyService;

	protected function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
	}

	private function getService(): SignatureRejectionPolicyService {
		return new SignatureRejectionPolicyService($this->policyService);
	}

	private function resolvedPolicy(mixed $effectiveValue): ResolvedPolicy {
		return (new ResolvedPolicy())
			->setPolicyKey(SignatureRejectionPolicy::KEY)
			->setEffectiveValue($effectiveValue)
			->setSourceScope('system');
	}

	private function fileWithSnapshot(mixed $effectiveValue): File {
		$file = new File();
		$file->setUserId('requester');
		$file->setMetadata([
			'policy_snapshot' => [
				SignatureRejectionPolicy::KEY => [
					'effectiveValue' => $effectiveValue,
					'sourceScope' => 'system',
				],
			],
		]);
		return $file;
	}

	public function testWithoutAFileTheSystemPolicyIsResolved(): void {
		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(SignatureRejectionPolicy::KEY, null)
			->willReturn($this->resolvedPolicy(['enabled' => true, 'comment_mode' => 'optional']));

		$this->assertSame(
			SignatureRejectionPolicyValue::normalize(['enabled' => true, 'comment_mode' => 'optional']),
			$this->getService()->getPolicyValue(),
		);
	}

	public function testPolicyIsResolvedForTheFileOwnerWhenThereIsNoSnapshot(): void {
		$file = new File();
		$file->setUserId('requester');

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(SignatureRejectionPolicy::KEY, 'requester')
			->willReturn($this->resolvedPolicy(['enabled' => true]));

		$this->assertTrue($this->getService()->isEnabled($file));
	}

	public function testPolicyIsResolvedForTheGivenUserWhenThereIsNoSnapshot(): void {
		$user = $this->createMock(IUser::class);

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(SignatureRejectionPolicy::KEY, $user)
			->willReturn($this->resolvedPolicy(['enabled' => true, 'cancel_workflow' => true]));
		$this->policyService->expects($this->never())->method('resolveForUserId');

		$this->assertTrue($this->getService()->cancelsWorkflow(null, $user));
	}

	public function testSnapshotWinsOverALaterPolicyChange(): void {
		$file = $this->fileWithSnapshot(['enabled' => true, 'comment_mode' => 'required']);

		$this->policyService->expects($this->never())->method('resolveForUserId');
		$this->policyService->expects($this->never())->method('resolveForUser');

		$this->assertSame(
			SignatureRejectionCommentMode::REQUIRED,
			$this->getService()->getCommentMode($file),
		);
	}

	public function testSnapshotIsNormalizedBeforeBeingUsed(): void {
		$file = $this->fileWithSnapshot('{"enabled":true,"comment_mode":"optional","allow_private_comment":true}');

		$this->assertSame([
			'enabled' => true,
			'comment_mode' => 'optional',
			'allow_private_comment' => true,
			'cancel_workflow' => false,
			'public_status' => false,
			'show_comment_on_validation' => false,
		], $this->getService()->getPolicyValue($file));
	}

	public function testMalformedSnapshotFallsBackToTheResolvedPolicy(): void {
		$file = new File();
		$file->setUserId('requester');
		$file->setMetadata(['policy_snapshot' => 'not-an-array']);

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->willReturn($this->resolvedPolicy(['enabled' => true]));

		$this->assertTrue($this->getService()->isEnabled($file));
	}

	public function testSnapshotWithoutTheRejectionEntryFallsBackToTheResolvedPolicy(): void {
		$file = new File();
		$file->setUserId('requester');
		$file->setMetadata(['policy_snapshot' => ['signer_geolocation' => ['effectiveValue' => ['mode' => 'required']]]]);

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->willReturn($this->resolvedPolicy(['enabled' => false]));

		$this->assertFalse($this->getService()->isEnabled($file));
	}

	public function testGetPolicyValueFromMetadataUsesTheSnapshot(): void {
		$this->policyService->expects($this->never())->method('resolveForUserId');

		$metadata = [
			'policy_snapshot' => [
				SignatureRejectionPolicy::KEY => [
					'effectiveValue' => ['enabled' => true, 'public_status' => true],
				],
			],
		];

		$this->assertTrue($this->getService()->getPolicyValueFromMetadata($metadata, 'requester')['public_status']);
	}

	public function testGetPolicyValueFromMetadataResolvesForTheRequesterWithoutSnapshot(): void {
		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(SignatureRejectionPolicy::KEY, 'requester')
			->willReturn($this->resolvedPolicy(['enabled' => true, 'public_status' => true]));

		$this->assertTrue($this->getService()->getPolicyValueFromMetadata([], 'requester')['public_status']);
	}
}
