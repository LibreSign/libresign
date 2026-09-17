<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureRejection;

use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyConfig;
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

	private function withPolicy(string $visibility, string $commentVisibility = 'requester'): void {
		$this->rejectionPolicyService
			->method('getConfig')
			->willReturn(SignatureRejectionPolicyConfig::fromValues(
				enabled: true,
				behavior: 'cancel',
				commentMode: 'optional',
				visibility: $visibility,
				commentVisibility: $commentVisibility,
			));
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
		$this->rejectionPolicyService->expects($this->never())->method('getConfig');

		$this->assertNull($this->getService()->buildSignerRejection($signRequest, null, true));
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
		$this->withPolicy(visibility: 'requester');

		$this->assertNull(
			$this->getService()->buildSignerRejection($this->rejectedSignRequest('Nope'), null, false),
		);
	}

	public function testRequesterAndSignerAlwaysSeeTheWholeRecord(): void {
		$this->withPolicy(visibility: 'requester');

		$this->assertSame(
			[
				'rejectedAt' => self::REJECTED_AT,
				'comment' => 'Nope',
				'commentPrivate' => true,
			],
			$this->getService()->buildSignerRejection(
				$this->rejectedSignRequest('Nope', true),
				null,
				true,
			),
		);
	}

	public function testOnlyTheTimestampIsExposedWhenThereIsNoComment(): void {
		$this->withPolicy(visibility: 'public', commentVisibility: 'public');

		$this->assertSame(
			['rejectedAt' => self::REJECTED_AT],
			$this->getService()->buildSignerRejection($this->rejectedSignRequest(), null, false),
		);
	}

	#[DataProvider('provideOtherReaderCases')]
	public function testCommentDisclosureToOtherReaders(
		string $visibility,
		string $commentVisibility,
		?string $comment,
		bool $commentPrivate,
		array $expected,
	): void {
		$this->withPolicy($visibility, $commentVisibility);

		$this->assertSame(
			$expected,
			$this->getService()->buildSignerRejection(
				$this->rejectedSignRequest($comment, $commentPrivate),
				null,
				false,
			),
		);
	}

	/**
	 * @return iterable<string, array{0: string, 1: string, 2: ?string, 3: bool, 4: array<string, mixed>}>
	 */
	public static function provideOtherReaderCases(): iterable {
		yield 'public rejection with a comment kept private' => [
			'public',
			'requester',
			'Nope',
			false,
			['rejectedAt' => self::REJECTED_AT],
		];

		yield 'public rejection and public comment' => [
			'public',
			'public',
			'Nope',
			false,
			['rejectedAt' => self::REJECTED_AT, 'comment' => 'Nope', 'commentPrivate' => false],
		];

		yield 'a comment the signer made private is never disclosed' => [
			'public',
			'public',
			'Nope',
			true,
			['rejectedAt' => self::REJECTED_AT],
		];

		yield 'empty comment is treated as no comment' => [
			'public',
			'public',
			'',
			false,
			['rejectedAt' => self::REJECTED_AT],
		];
	}
}
