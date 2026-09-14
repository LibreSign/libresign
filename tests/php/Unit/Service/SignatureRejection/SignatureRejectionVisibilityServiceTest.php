<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureRejection;

use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\SignerDisplayStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValue;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionPolicyService;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionVisibilityService;
use OCP\IL10N;
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
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($text, $params));
		return new SignatureRejectionVisibilityService($this->rejectionPolicyService, $l10n);
	}

	/** @param array<string, mixed> $policy */
	private function withPolicy(array $policy): void {
		$this->rejectionPolicyService
			->method('getPolicyValue')
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
		$this->rejectionPolicyService->expects($this->never())->method('getPolicyValue');

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

	private function signRequest(int $id, SignRequestStatus $status): SignRequest {
		$signRequest = new SignRequest();
		$signRequest->setId($id);
		$signRequest->setStatusEnum($status);
		if ($status === SignRequestStatus::REJECTED) {
			$signRequest->setRejectedAt(new \DateTime(self::REJECTED_AT));
		}
		return $signRequest;
	}

	public function testNoRejectionMeansNothingIsHidden(): void {
		$this->rejectionPolicyService->expects($this->never())->method('getPolicyValue');

		$hidden = $this->getService()->hasHiddenRejection(null, [
			$this->signRequest(1, SignRequestStatus::ABLE_TO_SIGN),
			$this->signRequest(2, SignRequestStatus::SIGNED),
		], []);

		$this->assertFalse($hidden);
	}

	public function testRejectionIsNotHiddenFromAPrivilegedViewerEvenWithAPrivateStatus(): void {
		$this->withPolicy(['enabled' => true, 'public_status' => false]);

		$hidden = $this->getService()->hasHiddenRejection(null, [
			$this->signRequest(1, SignRequestStatus::REJECTED),
			$this->signRequest(2, SignRequestStatus::ABLE_TO_SIGN),
		], [1]);

		$this->assertFalse($hidden);
	}

	public function testAPrivilegedRejectionDoesNotStopTheLookForAHiddenOne(): void {
		$this->withPolicy(['enabled' => true, 'public_status' => false]);

		$hidden = $this->getService()->hasHiddenRejection(null, [
			$this->signRequest(1, SignRequestStatus::REJECTED),
			$this->signRequest(2, SignRequestStatus::REJECTED),
		], [1]);

		$this->assertTrue($hidden);
	}

	public function testThePolicyIsResolvedOnceForAllTheRejectionsOfAFile(): void {
		$this->rejectionPolicyService->expects($this->once())
			->method('getPolicyValue')
			->willReturn(SignatureRejectionPolicyValue::normalize(['enabled' => true, 'public_status' => true]));

		$hidden = $this->getService()->hasHiddenRejection(null, [
			$this->signRequest(1, SignRequestStatus::REJECTED),
			$this->signRequest(2, SignRequestStatus::REJECTED),
		], []);

		$this->assertFalse($hidden);
	}

	#[DataProvider('publicStatusCases')]
	public function testRejectionIsHiddenFromOthersUnlessTheStatusIsPublic(bool $publicStatus, bool $expectedHidden): void {
		$this->withPolicy(['enabled' => true, 'public_status' => $publicStatus]);

		$hidden = $this->getService()->hasHiddenRejection(null, [
			$this->signRequest(1, SignRequestStatus::ABLE_TO_SIGN),
			$this->signRequest(2, SignRequestStatus::REJECTED),
		], [1]);

		$this->assertSame($expectedHidden, $hidden);
	}

	public static function publicStatusCases(): array {
		return [
			'private status hides the rejection' => [false, true],
			'public status discloses it' => [true, false],
		];
	}

	#[DataProvider('displayStatusMapping')]
	public function testPresentSignerMapsTheRealStateWhenNothingIsHidden(SignRequestStatus $status, SignerDisplayStatus $expected, string $label): void {
		$this->withPolicy(['enabled' => true, 'public_status' => true]);

		$presentation = $this->getService()->presentSigner($this->signRequest(1, $status), null, false, false);

		$this->assertSame($expected, $presentation->displayStatus);
		$this->assertSame($status->value, $presentation->status);
		$this->assertSame($label, $presentation->statusText);
	}

	public static function displayStatusMapping(): array {
		return [
			'draft' => [SignRequestStatus::DRAFT, SignerDisplayStatus::DRAFT, 'Draft'],
			'ready to sign' => [SignRequestStatus::ABLE_TO_SIGN, SignerDisplayStatus::READY_TO_SIGN, 'Ready to sign'],
			'signed' => [SignRequestStatus::SIGNED, SignerDisplayStatus::SIGNED, 'Signed'],
			'rejected' => [SignRequestStatus::REJECTED, SignerDisplayStatus::REJECTED, 'Rejected'],
		];
	}

	/**
	 * With a hidden rejection every unsigned signer looks the same to the
	 * viewer, whatever their real state, so nobody can be singled out.
	 */
	#[DataProvider('unsignedStates')]
	public function testPresentSignerRedactsEveryUnsignedSignerWhenARejectionIsHidden(SignRequestStatus $status): void {
		$this->withPolicy(['enabled' => true, 'public_status' => false]);

		$presentation = $this->getService()->presentSigner($this->signRequest(1, $status), null, false, true);

		$this->assertSame(SignerDisplayStatus::NOT_SIGNED, $presentation->displayStatus);
		$this->assertNull($presentation->status);
		$this->assertSame('Not signed', $presentation->statusText);
		$this->assertNull($presentation->rejection);
	}

	public static function unsignedStates(): array {
		return [
			'draft' => [SignRequestStatus::DRAFT],
			'ready to sign' => [SignRequestStatus::ABLE_TO_SIGN],
			'rejected' => [SignRequestStatus::REJECTED],
		];
	}

	public function testASignedSignerIsNeverRedacted(): void {
		$presentation = $this->getService()->presentSigner($this->signRequest(1, SignRequestStatus::SIGNED), null, false, true);

		$this->assertSame(SignerDisplayStatus::SIGNED, $presentation->displayStatus);
		$this->assertSame(SignRequestStatus::SIGNED->value, $presentation->status);
	}

	public function testTheViewerKeepsTheirOwnRejectionWhenAnotherRejectionIsHidden(): void {
		$this->withPolicy(['enabled' => true, 'public_status' => false]);

		$presentation = $this->getService()->presentSigner($this->rejectedSignRequest('My reason', true), null, true, true);

		$this->assertSame(SignerDisplayStatus::REJECTED, $presentation->displayStatus);
		$this->assertSame(SignRequestStatus::REJECTED->value, $presentation->status);
		$this->assertSame(['rejectedAt' => self::REJECTED_AT, 'comment' => 'My reason', 'commentPrivate' => true], $presentation->rejection);
	}

	/**
	 * A pending viewer who kept their real status could tell who rejected by
	 * comparing their own entry with the redacted ones, so being the signer
	 * does not lift the redaction of a pending entry.
	 */
	#[DataProvider('pendingStates')]
	public function testTheViewersOwnPendingEntryIsRedactedLikeTheOthers(SignRequestStatus $status): void {
		$this->withPolicy(['enabled' => true, 'public_status' => false]);

		$presentation = $this->getService()->presentSigner($this->signRequest(1, $status), null, true, true);

		$this->assertSame(SignerDisplayStatus::NOT_SIGNED, $presentation->displayStatus);
		$this->assertNull($presentation->status);
		$this->assertSame('Not signed', $presentation->statusText);
		$this->assertNull($presentation->rejection);
	}

	public static function pendingStates(): array {
		return [
			'draft' => [SignRequestStatus::DRAFT],
			'ready to sign' => [SignRequestStatus::ABLE_TO_SIGN],
		];
	}

	public function testPresentSignerCarriesTheRejectionObjectOfAVisibleRejection(): void {
		$this->withPolicy(['enabled' => true, 'comment_mode' => 'optional', 'public_status' => true, 'show_comment_on_validation' => true]);

		$presentation = $this->getService()->presentSigner($this->rejectedSignRequest('Public reason'), null, false, false);

		$this->assertSame(SignerDisplayStatus::REJECTED, $presentation->displayStatus);
		$this->assertSame(['rejectedAt' => self::REJECTED_AT, 'comment' => 'Public reason', 'commentPrivate' => false], $presentation->rejection);
	}

	public function testRejectionIsHiddenFromOtherReadersWhileTheStatusIsPrivate(): void {
		$this->withPolicy(['enabled' => true, 'public_status' => false]);

		$this->assertNull(
			$this->getService()->buildSignerRejection($this->rejectedSignRequest('Nope'), null, false),
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
				null,
				true,
			),
		);
	}

	public function testOnlyTheTimestampIsExposedWhenThereIsNoComment(): void {
		$this->withPolicy(['enabled' => true, 'public_status' => true, 'show_comment_on_validation' => true]);

		$this->assertSame(
			['rejectedAt' => self::REJECTED_AT],
			$this->getService()->buildSignerRejection($this->rejectedSignRequest(), null, false),
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
				null,
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
