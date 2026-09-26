<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Service\Policy\FrozenFilePolicySnapshotResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class FrozenFilePolicySnapshotResolverTest extends TestCase {
	private FileMapper&MockObject $fileMapper;

	protected function setUp(): void {
		parent::setUp();
		$this->fileMapper = $this->createMock(FileMapper::class);
	}

	/**
	 * @return FrozenFilePolicySnapshotResolver<array{mode: string}>
	 */
	private function getResolver(string $policyKey = 'signer_device_geolocation'): FrozenFilePolicySnapshotResolver {
		return new FrozenFilePolicySnapshotResolver(
			$this->fileMapper,
			$policyKey,
			static function (mixed $value): array {
				if (!is_array($value)) {
					return ['mode' => 'disabled'];
				}

				return ['mode' => (string)($value['mode'] ?? 'disabled')];
			},
		);
	}

	/** @param array{mode: string} $policyValue */
	private function fileWithSnapshot(array $policyValue, string $policyKey = 'signer_device_geolocation'): File {
		$file = new File();
		$file->setMetadata([
			'policy_snapshot' => [
				$policyKey => [
					'effectiveValue' => $policyValue,
					'sourceScope' => 'system',
				],
			],
		]);

		return $file;
	}

	public function testAbsentFileHasNoSnapshot(): void {
		$this->assertNull($this->getResolver()->findSnapshot(null));
	}

	public function testStandaloneFileWithoutSnapshotReturnsNull(): void {
		$this->assertNull($this->getResolver()->findSnapshot(new File()));
	}

	public function testStandaloneFileReturnsNormalizedSnapshot(): void {
		$this->assertSame(
			['mode' => 'required'],
			$this->getResolver()->findSnapshot($this->fileWithSnapshot(['mode' => 'required'])),
		);
	}

	public function testEnvelopeFallsBackToOldestChild(): void {
		$envelope = new File();
		$envelope->setId(1);
		$envelope->setNodeType('envelope');

		$newer = new File();
		$newer->setId(9);
		$newer->setMetadata($this->fileWithSnapshot(['mode' => 'disabled'])->getMetadata());

		$oldest = new File();
		$oldest->setId(2);
		$oldest->setMetadata($this->fileWithSnapshot(['mode' => 'required'])->getMetadata());

		$this->fileMapper
			->method('getChildrenFiles')
			->with(1)
			->willReturn([$newer, $oldest]);

		$this->assertSame(['mode' => 'required'], $this->getResolver()->findSnapshot($envelope));
	}

	public function testChildPrefersEnvelopeSnapshot(): void {
		$child = $this->fileWithSnapshot(['mode' => 'disabled']);
		$child->setId(2);
		$child->setParentFileId(1);

		$envelope = $this->fileWithSnapshot(['mode' => 'required']);
		$envelope->setId(1);

		$this->fileMapper
			->expects($this->once())
			->method('getById')
			->with(1)
			->willReturn($envelope);

		$this->assertSame(['mode' => 'required'], $this->getResolver()->findSnapshot($child));
	}
}
