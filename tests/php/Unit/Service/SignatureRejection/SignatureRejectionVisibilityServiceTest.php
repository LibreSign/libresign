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
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyConfig;
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
		$this->rejectionPolicyService->expects($this->never())->method('getConfig');

		$hidden = $this->getService()->hasHiddenRejection(null, [
			$this->signRequest(1, SignRequestStatus::ABLE_TO_SIGN),
			$this->signRequest(2, SignRequestStatus::SIGNED),
		], []);

		$this->assertFalse($hidden);
	}

	public function testRejectionIsNotHiddenFromAPrivilegedViewerEvenWithAPrivateStatus(): void {
		$this->withPolicy('requester');

		$hidden = $this->getService()->hasHiddenRejection(null, [
			$this->signRequest(1, SignRequestStatus::REJECTED),
			$this->signRequest(2, SignRequestStatus::ABLE_TO_SIGN),
		], [1]);

		$this->assertFalse($hidden);
	}

	public function testAPrivilegedRejectionDoesNotStopTheLookForAHiddenOne(): void {
		$this->withPolicy('requester');

		$hidden = $this->getService()->hasHiddenRejection(null, [
			$this->signRequest(1, SignRequestStatus::REJECTED),
			$this->signRequest(2, SignRequestStatus::REJECTED),
		], [1]);

		$this->assertTrue($hidden);
	}

	public function testThePolicyIsResolvedOnceForAllTheRejectionsOfAFile(): void {
		$this->rejectionPolicyService->expects($this->once())
			->method('getConfig')
			->willReturn(SignatureRejectionPolicyConfig::fromValues(enabled: true, visibility: 'public'));

		$hidden = $this->getService()->hasHiddenRejection(null, [
			$this->signRequest(1, SignRequestStatus::REJECTED),
			$this->signRequest(2, SignRequestStatus::REJECTED),
		], []);

		$this->assertFalse($hidden);
	}

	#[DataProvider('publicStatusCases')]
	public function testRejectionIsHiddenFromOthersUnlessTheStatusIsPublic(string $visibility, bool $expectedHidden): void {
		$this->withPolicy($visibility);

		$hidden = $this->getService()->hasHiddenRejection(null, [
			$this->signRequest(1, SignRequestStatus::ABLE_TO_SIGN),
			$this->signRequest(2, SignRequestStatus::REJECTED),
		], [1]);

		$this->assertSame($expectedHidden, $hidden);
	}

	public static function publicStatusCases(): array {
		return [
			'private status hides the rejection' => ['requester', true],
			'public status discloses it' => ['public', false],
		];
	}

	#[DataProvider('displayStatusMapping')]
	public function testPresentSignerMapsTheRealStateWhenNothingIsHidden(SignRequestStatus $status, SignerDisplayStatus $expected, string $label): void {
		$this->withPolicy('public');

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
			'observing' => [SignRequestStatus::OBSERVING, SignerDisplayStatus::OBSERVING, 'Observing'],
		];
	}

	/**
	 * With a hidden rejection every unsigned signer looks the same to the
	 * viewer, whatever their real state, so nobody can be singled out.
	 */
	#[DataProvider('unsignedStates')]
	public function testPresentSignerRedactsEveryUnsignedSignerWhenARejectionIsHidden(SignRequestStatus $status): void {
		$this->withPolicy('requester');

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

	/**
	 * A signed signer and an observer could not have rejected, so showing
	 * their real state does not point at anyone.
	 */
	#[DataProvider('statesThatCannotHideARejection')]
	public function testASignerWhoCouldNotHaveRejectedIsNeverRedacted(SignRequestStatus $status, SignerDisplayStatus $expected, string $label): void {
		$presentation = $this->getService()->presentSigner($this->signRequest(1, $status), null, false, true);

		$this->assertSame($expected, $presentation->displayStatus);
		$this->assertSame($status->value, $presentation->status);
		$this->assertSame($label, $presentation->statusText);
	}

	public static function statesThatCannotHideARejection(): array {
		return [
			'signed' => [SignRequestStatus::SIGNED, SignerDisplayStatus::SIGNED, 'Signed'],
			'observing' => [SignRequestStatus::OBSERVING, SignerDisplayStatus::OBSERVING, 'Observing'],
		];
	}

	public function testThePrivilegedViewerKeepsTheirOwnEntryWhenAnotherRejectionIsHidden(): void {
		$this->withPolicy('requester');

		$presentation = $this->getService()->presentSigner($this->rejectedSignRequest('My reason', true), null, true, true);

		$this->assertSame(SignerDisplayStatus::REJECTED, $presentation->displayStatus);
		$this->assertSame(SignRequestStatus::REJECTED->value, $presentation->status);
		$this->assertSame(['rejectedAt' => self::REJECTED_AT, 'comment' => 'My reason', 'commentPrivate' => true], $presentation->rejection);
	}

	public function testPresentSignerCarriesTheRejectionObjectOfAVisibleRejection(): void {
		$this->withPolicy('public', 'public');

		$presentation = $this->getService()->presentSigner($this->rejectedSignRequest('Public reason'), null, false, false);

		$this->assertSame(SignerDisplayStatus::REJECTED, $presentation->displayStatus);
		$this->assertSame(['rejectedAt' => self::REJECTED_AT, 'comment' => 'Public reason', 'commentPrivate' => false], $presentation->rejection);
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
