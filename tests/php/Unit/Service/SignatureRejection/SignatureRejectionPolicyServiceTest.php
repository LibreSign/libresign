<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureRejection;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\SignatureRejectionBehavior;
use OCA\Libresign\Enum\SignatureRejectionCommentMode;
use OCA\Libresign\Enum\SignatureRejectionVisibility;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyConfig;
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

	/**
	 * One snapshot entry per rejection setting, the way the file policy applier
	 * freezes them on the document.
	 *
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	private function snapshot(array $values): array {
		$policySnapshot = [];
		foreach ($values as $policyKey => $effectiveValue) {
			$policySnapshot[$policyKey] = [
				'effectiveValue' => $effectiveValue,
				'sourceScope' => 'request',
			];
		}

		return ['policy_snapshot' => $policySnapshot];
	}

	public function testWithoutAFileRejectionIsDisabled(): void {
		$this->assertSame(
			SignatureRejectionPolicyConfig::defaults()->toKeyedValues(),
			$this->getService()->getConfig()->toKeyedValues(),
		);
	}

	public function testARequestThatNeverOptedInKeepsRejectionDisabled(): void {
		$this->assertSame(
			SignatureRejectionPolicyConfig::defaults()->toKeyedValues(),
			$this->getService()->getConfig($this->file())->toKeyedValues(),
		);
	}

	public function testAMalformedSnapshotKeepsRejectionDisabled(): void {
		$this->assertFalse($this->getService()->isEnabled($this->file(['policy_snapshot' => 'not-an-array'])));
	}

	public function testASnapshotOfAnotherPolicyKeepsRejectionDisabled(): void {
		$file = $this->file(['policy_snapshot' => ['signer_geolocation' => ['effectiveValue' => ['mode' => 'required']]]]);

		$this->assertFalse($this->getService()->isEnabled($file));
	}

	public function testTheStoredConfigurationIsTheEffectiveOne(): void {
		$file = $this->file($this->snapshot([
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_BEHAVIOR => 'cancel',
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'required',
			SignatureRejectionPolicy::KEY_VISIBILITY => 'public',
			SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'participants',
		]));

		$service = $this->getService();
		$this->assertTrue($service->isEnabled($file));
		$this->assertSame(SignatureRejectionBehavior::CANCEL, $service->getBehavior($file));
		$this->assertSame(SignatureRejectionCommentMode::REQUIRED, $service->getCommentMode($file));
		$this->assertSame(SignatureRejectionVisibility::PUBLIC, $service->getVisibility($file));
		$this->assertSame(SignatureRejectionVisibility::PARTICIPANTS, $service->getCommentVisibility($file));
		$this->assertTrue($service->cancelsWorkflow($file));
	}

	public function testTheStoredValuesAreNormalizedBeforeBeingUsed(): void {
		$file = $this->file($this->snapshot([
			SignatureRejectionPolicy::KEY_ENABLED => 'true',
			SignatureRejectionPolicy::KEY_BEHAVIOR => 'continue',
			SignatureRejectionPolicy::KEY_COMMENT_MODE => ' optional ',
			SignatureRejectionPolicy::KEY_VISIBILITY => 'everyone',
		]));

		$this->assertSame([
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_BEHAVIOR => 'continue',
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
			SignatureRejectionPolicy::KEY_VISIBILITY => 'requester',
			SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'requester',
		], $this->getService()->getConfig($file)->toKeyedValues());
	}

	public function testAStoredCommentAudienceNeverOutgrowsTheRejectionAudience(): void {
		// Even a hand-written or stale snapshot cannot make the comment reach
		// further than the rejection it belongs to.
		$file = $this->file($this->snapshot([
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
			SignatureRejectionPolicy::KEY_VISIBILITY => 'requester',
			SignatureRejectionPolicy::KEY_COMMENT_VISIBILITY => 'public',
		]));

		$this->assertSame(SignatureRejectionVisibility::REQUESTER, $this->getService()->getCommentVisibility($file));
	}

	public function testEnvelopeReadsTheConfigurationStoredOnTheDocumentsItContains(): void {
		$envelope = $this->file();
		$envelope->setNodeType('envelope');

		$childWithoutSnapshot = new File();
		$childWithoutSnapshot->setId(2);

		$child = new File();
		$child->setId(3);
		$child->setMetadata($this->snapshot([
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'required',
		]));

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

	public function testEnvelopeWithoutAnyStoredConfigurationKeepsRejectionDisabled(): void {
		$envelope = $this->file();
		$envelope->setNodeType('envelope');

		$this->fileMapper->method('getChildrenFiles')->willReturn([]);

		$this->assertFalse($this->getService()->isEnabled($envelope));
	}

	public function testAnEnvelopeConfigurationGovernsTheDocumentsItContains(): void {
		// The requester edited the envelope after it was created, so the values on
		// the envelope are newer than the ones frozen on each document at creation.
		$child = $this->file($this->snapshot(SignatureRejectionPolicyConfig::defaults()->toKeyedValues()));
		$child->setId(2);
		$child->setParentFileId(1);

		$envelope = $this->file($this->snapshot([
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'required',
		]));

		$this->fileMapper->expects($this->once())->method('getById')->with(1)->willReturn($envelope);

		$this->assertSame(
			SignatureRejectionCommentMode::REQUIRED,
			$this->getService()->getCommentMode($child),
		);
	}

	public function testDocumentKeepsItsOwnConfigurationWhileTheEnvelopeHasNone(): void {
		$child = $this->file($this->snapshot([
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'optional',
		]));
		$child->setId(2);
		$child->setParentFileId(1);

		$this->fileMapper->method('getById')->with(1)->willReturn($this->file());

		$this->assertTrue($this->getService()->isEnabled($child));
	}

	public function testDocumentInsideAnEnvelopeFallsBackToTheEnvelopeConfiguration(): void {
		$child = $this->file();
		$child->setId(2);
		$child->setParentFileId(1);

		$envelope = $this->file($this->snapshot([
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_BEHAVIOR => 'cancel',
		]));

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

	public function testADocumentAddedLaterFollowsTheConfigurationOfTheEnvelope(): void {
		// A document added to an existing envelope is stamped disabled at creation;
		// it must still follow the configuration the envelope was created with.
		$addedLater = $this->file($this->snapshot(SignatureRejectionPolicyConfig::defaults()->toKeyedValues()));
		$addedLater->setId(9);
		$addedLater->setParentFileId(1);

		$envelope = $this->file();
		$envelope->setNodeType('envelope');

		$oldest = new File();
		$oldest->setId(2);
		$oldest->setMetadata($this->snapshot([
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'required',
		]));

		$this->fileMapper->method('getById')->with(1)->willReturn($envelope);
		$this->fileMapper->method('getChildrenFiles')->with(1)->willReturn([$addedLater, $oldest]);

		$this->assertTrue($this->getService()->isEnabled($addedLater));
	}

	public function testTheOldestDocumentAnswersForTheEnvelope(): void {
		$envelope = $this->file();
		$envelope->setNodeType('envelope');

		$oldest = new File();
		$oldest->setId(2);
		$oldest->setMetadata($this->snapshot([
			SignatureRejectionPolicy::KEY_ENABLED => true,
			SignatureRejectionPolicy::KEY_COMMENT_MODE => 'required',
		]));

		$addedLater = new File();
		$addedLater->setId(9);
		$addedLater->setMetadata($this->snapshot(SignatureRejectionPolicyConfig::defaults()->toKeyedValues()));

		// Returned out of order on purpose: the resolution must not depend on it.
		$this->fileMapper->method('getChildrenFiles')->with(1)->willReturn([$addedLater, $oldest]);

		$this->assertTrue($this->getService()->isEnabled($envelope));
	}
}
