<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureRejection;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\SignatureRejectionCommentMode;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValue;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionPolicyService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignatureRejectionPolicyServiceTest extends TestCase {
	private FileMapper&MockObject $fileMapper;

	protected function setUp(): void {
		parent::setUp();
		$this->fileMapper = $this->createMock(FileMapper::class);
	}

	private function getService(): SignatureRejectionPolicyService {
		return new SignatureRejectionPolicyService($this->fileMapper);
	}

	/** @param array<string, mixed> $metadata */
	private function file(array $metadata = []): File {
		$file = new File();
		$file->setId(1);
		$file->setUserId('requester');
		if ($metadata !== []) {
			$file->setMetadata($metadata);
		}
		return $file;
	}

	/** @return array<string, mixed> */
	private function snapshot(mixed $effectiveValue): array {
		return [
			'policy_snapshot' => [
				SignatureRejectionPolicy::KEY => [
					'effectiveValue' => $effectiveValue,
					'sourceScope' => 'request',
				],
			],
		];
	}

	public function testWithoutAFileRejectionIsDisabled(): void {
		$this->assertSame(SignatureRejectionPolicyValue::defaults(), $this->getService()->getPolicyValue());
	}

	public function testARequestThatNeverOptedInKeepsRejectionDisabled(): void {
		$this->assertSame(
			SignatureRejectionPolicyValue::defaults(),
			$this->getService()->getPolicyValue($this->file()),
		);
	}

	public function testAMalformedSnapshotKeepsRejectionDisabled(): void {
		$this->assertFalse($this->getService()->isEnabled($this->file(['policy_snapshot' => 'not-an-array'])));
	}

	public function testASnapshotOfAnotherPolicyKeepsRejectionDisabled(): void {
		$file = $this->file(['policy_snapshot' => ['signer_geolocation' => ['effectiveValue' => ['mode' => 'required']]]]);

		$this->assertFalse($this->getService()->isEnabled($file));
	}

	public function testTheStoredValueIsTheEffectiveValue(): void {
		$file = $this->file($this->snapshot(['enabled' => true, 'comment_mode' => 'required', 'cancel_workflow' => true]));

		$service = $this->getService();
		$this->assertTrue($service->isEnabled($file));
		$this->assertSame(SignatureRejectionCommentMode::REQUIRED, $service->getCommentMode($file));
		$this->assertTrue($service->cancelsWorkflow($file));
	}

	public function testTheStoredValueIsNormalizedBeforeBeingUsed(): void {
		$file = $this->file($this->snapshot('{"enabled":true,"comment_mode":"optional","cancel_workflow":true}'));

		$this->assertSame([
			'enabled' => true,
			'comment_mode' => 'optional',
			'cancel_workflow' => true,
			'public_status' => false,
			'show_comment_on_validation' => false,
		], $this->getService()->getPolicyValue($file));
	}

	public function testEnvelopeReadsTheValueStoredOnTheDocumentsItContains(): void {
		$envelope = $this->file();
		$envelope->setNodeType('envelope');

		$childWithoutSnapshot = new File();
		$childWithoutSnapshot->setId(2);

		$child = new File();
		$child->setId(3);
		$child->setMetadata($this->snapshot(['enabled' => true, 'comment_mode' => 'required']));

		$this->fileMapper
			->expects($this->once())
			->method('getChildrenFiles')
			->with(1)
			->willReturn([$childWithoutSnapshot, $child]);

		$this->assertSame(
			SignatureRejectionCommentMode::REQUIRED,
			$this->getService()->getCommentMode($envelope),
		);
	}

	public function testEnvelopeWithoutAnyStoredValueKeepsRejectionDisabled(): void {
		$envelope = $this->file();
		$envelope->setNodeType('envelope');

		$this->fileMapper->method('getChildrenFiles')->willReturn([]);

		$this->assertFalse($this->getService()->isEnabled($envelope));
	}

	public function testAnEnvelopeValueGovernsTheDocumentsItContains(): void {
		// The requester edited the envelope after it was created, so the value on the
		// envelope is newer than the one frozen on each document at creation time.
		$child = $this->file($this->snapshot(SignatureRejectionPolicyValue::defaults()));
		$child->setId(2);
		$child->setParentFileId(1);

		$envelope = $this->file($this->snapshot(['enabled' => true, 'comment_mode' => 'required']));

		$this->fileMapper->expects($this->once())->method('getById')->with(1)->willReturn($envelope);

		$this->assertSame(
			SignatureRejectionPolicyValue::normalize(['enabled' => true, 'comment_mode' => 'required']),
			$this->getService()->getPolicyValue($child),
		);
	}

	public function testDocumentKeepsItsOwnValueWhileTheEnvelopeHasNone(): void {
		$child = $this->file($this->snapshot(['enabled' => true, 'comment_mode' => 'optional']));
		$child->setId(2);
		$child->setParentFileId(1);

		$this->fileMapper->method('getById')->with(1)->willReturn($this->file());

		$this->assertTrue($this->getService()->isEnabled($child));
	}

	public function testDocumentInsideAnEnvelopeFallsBackToTheEnvelopeValue(): void {
		$child = $this->file();
		$child->setId(2);
		$child->setParentFileId(1);

		$envelope = $this->file($this->snapshot(['enabled' => true, 'cancel_workflow' => true]));

		$this->fileMapper->expects($this->once())->method('getById')->with(1)->willReturn($envelope);

		$this->assertTrue($this->getService()->cancelsWorkflow($child));
	}

	public function testAnUnreadableParentKeepsRejectionDisabled(): void {
		$child = $this->file();
		$child->setId(2);
		$child->setParentFileId(1);

		$this->fileMapper->method('getById')->willThrowException(new \RuntimeException('gone'));

		$this->assertFalse($this->getService()->isEnabled($child));
	}

	public function testADocumentAddedLaterFollowsTheValueOfTheEnvelope(): void {
		// A document added to an existing envelope is stamped disabled at creation;
		// it must still follow the value the envelope was created with.
		$addedLater = $this->file($this->snapshot(SignatureRejectionPolicyValue::defaults()));
		$addedLater->setId(9);
		$addedLater->setParentFileId(1);

		$envelope = $this->file();
		$envelope->setNodeType('envelope');

		$oldest = new File();
		$oldest->setId(2);
		$oldest->setMetadata($this->snapshot(['enabled' => true, 'comment_mode' => 'required']));

		$this->fileMapper->method('getById')->with(1)->willReturn($envelope);
		$this->fileMapper->method('getChildrenFiles')->with(1)->willReturn([$addedLater, $oldest]);

		$this->assertTrue($this->getService()->isEnabled($addedLater));
	}

	public function testTheOldestDocumentAnswersForTheEnvelope(): void {
		$envelope = $this->file();
		$envelope->setNodeType('envelope');

		$oldest = new File();
		$oldest->setId(2);
		$oldest->setMetadata($this->snapshot(['enabled' => true, 'comment_mode' => 'required']));

		$addedLater = new File();
		$addedLater->setId(9);
		$addedLater->setMetadata($this->snapshot(SignatureRejectionPolicyValue::defaults()));

		// Returned out of order on purpose: the resolution must not depend on it.
		$this->fileMapper->method('getChildrenFiles')->with(1)->willReturn([$addedLater, $oldest]);

		$this->assertTrue($this->getService()->isEnabled($envelope));
	}
}
