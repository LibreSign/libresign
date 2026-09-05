<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureRejection;

use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValue;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionPolicyService;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionVisibilityService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignatureRejectionVisibilityServiceTest extends TestCase {
	private const REJECTED_AT = '2026-09-06T10:00:00+00:00';

	private SignatureRejectionPolicyService&MockObject $rejectionPolicyService;

	protected function setUp(): void {
		parent::setUp();
		$this->rejectionPolicyService = $this->createMock(SignatureRejectionPolicyService::class);
	}

	private function getService(): SignatureRejectionVisibilityService {
		return new SignatureRejectionVisibilityService($this->rejectionPolicyService);
	}

	/** @param array<string, mixed> $policy */
	private function withPolicy(array $policy): void {
		$this->rejectionPolicyService
			->method('getPolicyValueFromMetadata')
			->willReturn(SignatureRejectionPolicyValue::normalize($policy));
	}

	private function rejectedSignRequest(?string $comment = null, bool $commentPrivate = false): SignRequest {
		$signRequest = new SignRequest();
		$signRequest->setId(1);
		$signRequest->setStatusEnum(SignRequestStatus::REJECTED);
		$signRequest->setRejectedAt(new \DateTime(self::REJECTED_AT));
		$signRequest->setRejectionComment($comment);
		$signRequest->setRejectionCommentPrivate($commentPrivate);
		return $signRequest;
	}

	#[DataProvider('provideNonRejectedSigners')]
	public function testNothingIsExposedForASignerThatDidNotReject(SignRequest $signRequest): void {
		$this->rejectionPolicyService->expects($this->never())->method('getPolicyValueFromMetadata');

		$this->assertNull($this->getService()->buildSignerRejection($signRequest, [], 'requester', true));
	}

	/**
	 * @return iterable<string, array{0: SignRequest}>
	 */
	public static function provideNonRejectedSigners(): iterable {
		$pending = new SignRequest();
		$pending->setStatusEnum(SignRequestStatus::ABLE_TO_SIGN);
		yield 'pending signer' => [$pending];

		$signed = new SignRequest();
		$signed->setStatusEnum(SignRequestStatus::SIGNED);
		yield 'signed signer' => [$signed];

		$withoutTimestamp = new SignRequest();
		$withoutTimestamp->setStatusEnum(SignRequestStatus::REJECTED);
		yield 'rejected without a stored timestamp' => [$withoutTimestamp];
	}

	public function testRejectionIsHiddenFromOtherReadersWhileTheStatusIsPrivate(): void {
		$this->withPolicy(['enabled' => true, 'public_status' => false]);

		$this->assertNull(
			$this->getService()->buildSignerRejection($this->rejectedSignRequest('Nope'), [], 'requester', false),
		);
	}

	public function testRequesterAndSignerAlwaysSeeTheWholeRecord(): void {
		$this->withPolicy(['enabled' => true, 'public_status' => false]);

		$this->assertSame(
			[
				'rejectedAt' => self::REJECTED_AT,
				'comment' => 'Nope',
				'commentPrivate' => true,
			],
			$this->getService()->buildSignerRejection(
				$this->rejectedSignRequest('Nope', true),
				[],
				'requester',
				true,
			),
		);
	}

	public function testOnlyTheTimestampIsExposedWhenThereIsNoComment(): void {
		$this->withPolicy(['enabled' => true, 'public_status' => true, 'show_comment_on_validation' => true]);

		$this->assertSame(
			['rejectedAt' => self::REJECTED_AT],
			$this->getService()->buildSignerRejection($this->rejectedSignRequest(), [], 'requester', false),
		);
	}

	#[DataProvider('provideOtherReaderCases')]
	public function testCommentDisclosureToOtherReaders(
		array $policy,
		?string $comment,
		bool $commentPrivate,
		array $expected,
	): void {
		$this->withPolicy($policy);

		$this->assertSame(
			$expected,
			$this->getService()->buildSignerRejection(
				$this->rejectedSignRequest($comment, $commentPrivate),
				[],
				'requester',
				false,
			),
		);
	}

	/**
	 * @return iterable<string, array{0: array<string, mixed>, 1: ?string, 2: bool, 3: array<string, mixed>}>
	 */
	public static function provideOtherReaderCases(): iterable {
		yield 'public status without comment disclosure' => [
			['enabled' => true, 'comment_mode' => 'optional', 'public_status' => true, 'show_comment_on_validation' => false],
			'Nope',
			false,
			['rejectedAt' => self::REJECTED_AT],
		];

		yield 'public status with comment disclosure' => [
			['enabled' => true, 'comment_mode' => 'optional', 'public_status' => true, 'show_comment_on_validation' => true],
			'Nope',
			false,
			['rejectedAt' => self::REJECTED_AT, 'comment' => 'Nope', 'commentPrivate' => false],
		];

		yield 'a private comment is never disclosed' => [
			[
				'enabled' => true,
				'comment_mode' => 'optional',
				'allow_private_comment' => true,
				'public_status' => true,
				'show_comment_on_validation' => true,
			],
			'Nope',
			true,
			['rejectedAt' => self::REJECTED_AT],
		];

		yield 'empty comment is treated as no comment' => [
			['enabled' => true, 'comment_mode' => 'optional', 'public_status' => true, 'show_comment_on_validation' => true],
			'',
			false,
			['rejectedAt' => self::REJECTED_AT],
		];
	}
}
