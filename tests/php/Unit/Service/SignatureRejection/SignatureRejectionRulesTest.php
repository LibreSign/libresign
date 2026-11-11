<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureRejection;

use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyConfig;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionRules;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SignatureRejectionRulesTest extends TestCase {
	use SignatureRejectionSamples;

	private SignatureRejectionRules $rules;
	private \PHPUnit\Framework\MockObject\MockObject $policies;

	protected function setUp(): void {
		$this->policies = $this->createMock(\OCA\Libresign\Service\SignatureRejection\SignatureRejectionPolicyService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->rules = new SignatureRejectionRules($this->policies, $l10n);
	}

	private function withPolicy(SignatureRejectionPolicyConfig $policy): void {
		$this->policies->method('getConfig')->willReturn($policy);
	}

	public function testDisabledPolicyPreventsRejection(): void {
		$this->withPolicy(SignatureRejectionPolicyConfig::defaults());
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Signature rejection is not enabled for this document.');

		$this->rules->validate($this->file(), $this->signer(), null, false);
	}

	/** @return array<string, array{int, string}> */
	public static function closedStatuses(): array {
		return [
			'already canceled' => [FileStatus::CANCELED->value, 'The signing workflow of this document is already closed.'],
			'draft' => [FileStatus::DRAFT->value, 'This document is not open for signature rejection.'],
			'signed' => [FileStatus::SIGNED->value, 'This document is not open for signature rejection.'],
			'deleted' => [FileStatus::DELETED->value, 'This document is not open for signature rejection.'],
		];
	}

	#[DataProvider('closedStatuses')]
	public function testClosedWorkflowRejectsInput(int $status, string $message): void {
		$this->withPolicy(self::policy());
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage($message);

		$this->rules->validate($this->file($status), $this->signer(), null, false);
	}

	public function testSignedSignerCannotReject(): void {
		$this->withPolicy(self::policy());
		$signer = $this->signer();
		$signer->setSigned(new \DateTime(self::REJECTED_AT));
		$this->expectExceptionMessage('File already signed by you');

		$this->rules->validate($this->file(), $signer, null, false);
	}

	public function testAlreadyRejectedSignerCannotRejectAgain(): void {
		$this->withPolicy(self::policy());
		$this->expectExceptionMessage('You already rejected this signature request.');

		$this->rules->validate($this->file(), $this->signer(SignRequestStatus::REJECTED->value), null, false);
	}

	public function testDisabledCommentsDoNotAcceptContent(): void {
		$this->withPolicy(self::policy());
		$this->expectExceptionMessage('Rejection comments are not allowed for this document.');

		$this->rules->validate($this->file(), $this->signer(), 'My private explanation', true);
	}

	/** @return array<string, array{?string}> */
	public static function absentComments(): array {
		return ['null' => [null], 'empty' => [''], 'whitespace' => ['   ']];
	}

	#[DataProvider('absentComments')]
	public function testRequiredCommentCannotBeBlank(?string $comment): void {
		$this->withPolicy(self::policy(commentMode: 'required'));
		$this->expectExceptionMessage('A comment is required to reject this signature request.');

		$this->rules->validate($this->file(), $this->signer(), $comment, false);
	}

	public function testOverlongCommentIsRejected(): void {
		$this->withPolicy(self::policy(commentMode: 'optional'));
		$this->expectExceptionMessage('The rejection comment must have at most');

		$this->rules->validate($this->file(), $this->signer(), str_repeat('a', SignatureRejectionRules::MAX_COMMENT_LENGTH + 1), false);
	}

	/** @return array<string, array{bool}> */
	public static function privacyChoices(): array {
		return ['private' => [true], 'public' => [false]];
	}

	#[DataProvider('privacyChoices')]
	public function testSignerControlsCommentPrivacy(bool $private): void {
		$this->withPolicy(self::policy(behavior: 'cancel', commentMode: 'optional'));
		$result = $this->rules->validate($this->file(), $this->signer(), '  reason  ', $private);

		$this->assertSame('reason', $result->comment);
		$this->assertSame($private, $result->commentIsPrivate);
		$this->assertTrue($result->cancelsWorkflow);
	}

	public function testEmptyCommentCannotBeMarkedPrivate(): void {
		$this->withPolicy(self::policy(commentMode: 'optional'));
		$result = $this->rules->validate($this->file(), $this->signer(), ' ', true);

		$this->assertNull($result->comment);
		$this->assertFalse($result->commentIsPrivate);
		$this->assertFalse($result->cancelsWorkflow);
	}

	public function testMaximumLengthCommentIsAccepted(): void {
		$this->withPolicy(self::policy(commentMode: 'required'));
		$comment = str_repeat('x', SignatureRejectionRules::MAX_COMMENT_LENGTH);
		$result = $this->rules->validate($this->file(), $this->signer(), $comment, false);
		$this->assertSame($comment, $result->comment);
	}
}
