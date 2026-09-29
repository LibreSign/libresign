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
use OCA\Libresign\Service\SignatureRejection\RejectionViewer;
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

	/** Neither the requester nor a signer of the file: the public audience. */
	private static function outsider(): RejectionViewer {
		return new RejectionViewer(false, []);
	}

	private static function requester(): RejectionViewer {
		return new RejectionViewer(true, []);
	}

	/** The signer behind the sign request with this id, a participant of the workflow. */
	private static function signerOwning(int $signRequestId): RejectionViewer {
		$signRequest = new SignRequest();
		$signRequest->setId($signRequestId);
		return new RejectionViewer(false, [$signRequest]);
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

		$this->assertNull($this->getService()->buildSignerRejection($signRequest, null, self::requester()));
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
		], self::outsider());

		$this->assertFalse($hidden);
	}

	public function testRejectionIsNotHiddenFromAPrivilegedViewerEvenWithAPrivateStatus(): void {
		$this->withPolicy('requester');

		$hidden = $this->getService()->hasHiddenRejection(null, [
			$this->signRequest(1, SignRequestStatus::REJECTED),
			$this->signRequest(2, SignRequestStatus::ABLE_TO_SIGN),
		], self::signerOwning(1));

		$this->assertFalse($hidden);
	}

	public function testAPrivilegedRejectionDoesNotStopTheLookForAHiddenOne(): void {
		$this->withPolicy('requester');

		$hidden = $this->getService()->hasHiddenRejection(null, [
			$this->signRequest(1, SignRequestStatus::REJECTED),
			$this->signRequest(2, SignRequestStatus::REJECTED),
		], self::signerOwning(1));

		$this->assertTrue($hidden);
	}

	public function testThePolicyIsResolvedOnceForAllTheRejectionsOfAFile(): void {
		$this->rejectionPolicyService->expects($this->once())
			->method('getConfig')
			->willReturn(SignatureRejectionPolicyConfig::fromValues(enabled: true, visibility: 'public'));

		$hidden = $this->getService()->hasHiddenRejection(null, [
			$this->signRequest(1, SignRequestStatus::REJECTED),
			$this->signRequest(2, SignRequestStatus::REJECTED),
		], self::outsider());

		$this->assertFalse($hidden);
	}

	#[DataProvider('publicStatusCases')]
	public function testRejectionIsHiddenFromOthersUnlessTheStatusIsPublic(string $visibility, bool $expectedHidden): void {
		$this->withPolicy($visibility);

		$hidden = $this->getService()->hasHiddenRejection(null, [
			$this->signRequest(1, SignRequestStatus::ABLE_TO_SIGN),
			$this->signRequest(2, SignRequestStatus::REJECTED),
		], self::signerOwning(1));

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

		$presentation = $this->getService()->presentSigner($this->signRequest(1, $status), null, self::outsider(), false);

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

		$presentation = $this->getService()->presentSigner($this->signRequest(1, $status), null, self::outsider(), true);

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
		$presentation = $this->getService()->presentSigner($this->signRequest(1, $status), null, self::outsider(), true);

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

	public function testTheViewerKeepsTheirOwnRejectionWhenAnotherRejectionIsHidden(): void {
		$this->withPolicy('requester');

		$presentation = $this->getService()->presentSigner($this->rejectedSignRequest('My reason', true), null, self::signerOwning(1), true);

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
		$this->withPolicy('requester');

		$presentation = $this->getService()->presentSigner($this->signRequest(1, $status), null, self::signerOwning(1), true);

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
		$this->withPolicy('public', 'public');

		$presentation = $this->getService()->presentSigner($this->rejectedSignRequest('Public reason'), null, self::outsider(), false);

		$this->assertSame(SignerDisplayStatus::REJECTED, $presentation->displayStatus);
		$this->assertSame(['rejectedAt' => self::REJECTED_AT, 'comment' => 'Public reason', 'commentPrivate' => false], $presentation->rejection);
	}

	public function testRejectionIsHiddenFromOtherReadersWhileTheStatusIsPrivate(): void {
		$this->withPolicy(visibility: 'requester');

		$this->assertNull(
			$this->getService()->buildSignerRejection($this->rejectedSignRequest('Nope'), null, self::outsider()),
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
				self::signerOwning(1),
			),
		);
	}

	public function testOnlyTheTimestampIsExposedWhenThereIsNoComment(): void {
		$this->withPolicy(visibility: 'public', commentVisibility: 'public');

		$this->assertSame(
			['rejectedAt' => self::REJECTED_AT],
			$this->getService()->buildSignerRejection($this->rejectedSignRequest(), null, self::outsider()),
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
				self::outsider(),
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

	/**
	 * `rejection_visibility` is the widest audience that learns about a
	 * rejection: a participant is reached from `participants` up, anybody
	 * else only by `public`.
	 */
	#[DataProvider('provideAudienceCases')]
	public function testTheRejectionReachesOnlyTheAudiencesItIsVisibleTo(string $visibility, bool $participant, bool $expectedVisible): void {
		$this->withPolicy($visibility);
		$viewer = $participant ? self::signerOwning(1) : self::outsider();
		$rejected = $this->rejectedSignRequest();
		$rejected->setId(2);

		$hidden = $this->getService()->hasHiddenRejection(null, [
			$this->signRequest(1, SignRequestStatus::ABLE_TO_SIGN),
			$rejected,
		], $viewer);

		$this->assertSame(!$expectedVisible, $hidden);
		$this->assertSame(
			$expectedVisible ? ['rejectedAt' => self::REJECTED_AT] : null,
			$this->getService()->buildSignerRejection($rejected, null, $viewer),
		);
	}

	/**
	 * @return iterable<string, array{0: string, 1: bool, 2: bool}>
	 */
	public static function provideAudienceCases(): iterable {
		yield 'requester level, another signer' => ['requester', true, false];
		yield 'requester level, the public' => ['requester', false, false];
		yield 'participants level, another signer' => ['participants', true, true];
		yield 'participants level, the public' => ['participants', false, false];
		yield 'public level, another signer' => ['public', true, true];
		yield 'public level, the public' => ['public', false, true];
	}

	#[DataProvider('provideCommentAudienceCases')]
	public function testTheCommentReachesOnlyTheAudiencesItIsVisibleTo(
		string $visibility,
		string $commentVisibility,
		bool $participant,
		bool $commentPrivate,
		?array $expected,
	): void {
		$this->withPolicy($visibility, $commentVisibility);
		$rejected = $this->rejectedSignRequest('Nope', $commentPrivate);
		$rejected->setId(2);

		$this->assertSame(
			$expected,
			$this->getService()->buildSignerRejection($rejected, null, $participant ? self::signerOwning(1) : self::outsider()),
		);
	}

	/**
	 * @return iterable<string, array{0: string, 1: string, 2: bool, 3: bool, 4: array<string, mixed>|null}>
	 */
	public static function provideCommentAudienceCases(): iterable {
		$withComment = ['rejectedAt' => self::REJECTED_AT, 'comment' => 'Nope', 'commentPrivate' => false];
		$withoutComment = ['rejectedAt' => self::REJECTED_AT];

		yield 'comment shared with the participants reaches another signer' => ['participants', 'participants', true, false, $withComment];
		yield 'comment kept for the requester is not shown to another signer' => ['participants', 'requester', true, false, $withoutComment];
		yield 'a rejection kept among the participants tells the public nothing' => ['participants', 'participants', false, false, null];
		yield 'a public rejection keeps a participants comment from the public' => ['public', 'participants', false, false, $withoutComment];
		yield 'a public rejection shows a participants comment to another signer' => ['public', 'participants', true, false, $withComment];
		yield 'a comment the signer made private stays from another signer' => ['public', 'public', true, true, $withoutComment];
	}

	/**
	 * With a rejection shared among the participants, the other signers see
	 * the real states, while the public still sees every unsigned signer the
	 * same way.
	 */
	public function testARejectionSharedWithTheParticipantsIsStillRedactedForThePublic(): void {
		$this->withPolicy('participants');
		$pending = $this->signRequest(1, SignRequestStatus::ABLE_TO_SIGN);
		$rejected = $this->signRequest(2, SignRequestStatus::REJECTED);
		$signers = [$pending, $rejected];
		$service = $this->getService();

		$participant = self::signerOwning(1);
		$hiddenForParticipant = $service->hasHiddenRejection(null, $signers, $participant);
		$this->assertFalse($hiddenForParticipant);
		$this->assertSame(SignerDisplayStatus::REJECTED, $service->presentSigner($rejected, null, $participant, $hiddenForParticipant)->displayStatus);
		$this->assertSame(SignerDisplayStatus::READY_TO_SIGN, $service->presentSigner($pending, null, $participant, $hiddenForParticipant)->displayStatus);

		$public = self::outsider();
		$hiddenForPublic = $service->hasHiddenRejection(null, $signers, $public);
		$this->assertTrue($hiddenForPublic);
		$this->assertSame(SignerDisplayStatus::NOT_SIGNED, $service->presentSigner($rejected, null, $public, $hiddenForPublic)->displayStatus);
		$this->assertSame(SignerDisplayStatus::NOT_SIGNED, $service->presentSigner($pending, null, $public, $hiddenForPublic)->displayStatus);
	}
}
