<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\DocMdp;

use OCA\Libresign\Db\File;
use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\DocMdp\StoredLevel;
use PHPUnit\Framework\TestCase;

final class StoredLevelTest extends TestCase {
	public function testDraftReevaluatesOnFirstSendThenKeepsLevelAfterReturningToDraft(): void {
		$file = new File();
		$file->setStatus(FileStatus::DRAFT->value);
		StoredLevel::initialize($file, DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED);

		$this->assertNull(StoredLevel::forSigning($file));
		StoredLevel::freeze($file, DocMdpLevel::CERTIFIED_FORM_FILLING);
		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING, StoredLevel::forSigning($file));

		$file->setStatus(FileStatus::DRAFT->value);
		StoredLevel::freeze($file, DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED);
		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING, StoredLevel::forSigning($file));
	}

	public function testExplicitNoCertificationRemainsAuthoritativeAfterGlobalSettingChanges(): void {
		$file = new File();
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		StoredLevel::initialize($file, DocMdpLevel::NOT_CERTIFIED);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED, StoredLevel::forSigning($file));
		$this->assertTrue(($file->getMetadata() ?? [])['docmdp_level_frozen']);
	}

	public function testLegacyZeroWithoutProvenancePreservesOriginalGlobalFallback(): void {
		$file = new File();
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$file->setDocmdpLevelEnum(DocMdpLevel::NOT_CERTIFIED);

		$this->assertNull(StoredLevel::forSigning($file));
	}

	public function testLegacyNonZeroKeepsOriginalStoredCertification(): void {
		$file = new File();
		$file->setStatus(FileStatus::SIGNED->value);
		$file->setDocmdpLevelEnum(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED);

		$this->assertSame(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED, StoredLevel::forSigning($file));
	}

	public function testDeletingUnsentDraftDoesNotFreezeItsPolicy(): void {
		$file = new File();
		$file->setStatus(FileStatus::DELETED->value);
		$this->assertFalse(StoredLevel::isFrozen($file));
	}
}
