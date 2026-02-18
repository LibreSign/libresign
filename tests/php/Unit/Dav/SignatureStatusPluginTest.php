<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Dav;

use OCA\DAV\Connector\Sabre\Directory;
use OCA\DAV\Connector\Sabre\File as DavFile;
use OCA\Libresign\Dav\SignatureStatusPlugin;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;

final class SignatureStatusPluginTest extends TestCase {
	#[DataProvider('providerPropFindSkipsHandle')]
	public function testPropFindSkipsHandle(
		string $nodeClass,
		?bool $isLibresignFile,
		bool $throwOnSet,
	): void {
		$fileService = $this->createMock(FileService::class);

		if ($nodeClass === INode::class) {
			$fileService
				->expects($this->never())
				->method('isLibresignFile');
		} else {
			$fileService
				->expects($this->once())
				->method('isLibresignFile')
				->willReturn($isLibresignFile);
		}

		if ($nodeClass !== INode::class && $isLibresignFile === true) {
			$setter = $fileService
				->expects($this->once())
				->method('setFileByNodeId');
			if ($throwOnSet) {
				$setter->willThrowException(new \RuntimeException('invalid mapping'));
			}
		} else {
			$fileService
				->expects($this->never())
				->method('setFileByNodeId');
		}

		$node = $this->createMock($nodeClass);
		if ($node instanceof DavFile || $node instanceof Directory) {
			$node->method('getId')->willReturn(101);
		}

		$propFind = $this->createMock(PropFind::class);
		$propFind
			->expects($this->never())
			->method('handle');

		$plugin = $this->getPlugin($fileService);
		$plugin->propFind($propFind, $node);
	}

	public static function providerPropFindSkipsHandle(): array {
		return [
			'non-file node' => [INode::class, null, false],
			'non-libresign file' => [DavFile::class, false, false],
			'invalid mapping throws' => [DavFile::class, true, true],
			'non-libresign directory' => [Directory::class, false, false],
		];
	}

	public function testPropFindHandlesWhenLibresignFileIsValid(): void {
		$fileService = $this->createMock(FileService::class);
		$fileService
			->method('isLibresignFile')
			->willReturn(true);
		$fileService
			->method('getStatus')
			->willReturn(1);
		$fileService
			->method('getSignedNodeId')
			->willReturn(202);

		$fileService
			->expects($this->once())
			->method('setFileByNodeId')
			->with(101);

		$node = $this->createMock(DavFile::class);
		$node->method('getId')->willReturn(101);

		$propFind = $this->createMock(PropFind::class);
		$expected = [
			['{http://nextcloud.org/ns}libresign-signature-status', 1],
			['{http://nextcloud.org/ns}libresign-signed-node-id', 202],
		];
		$callIndex = 0;
		$propFind
			->expects($this->exactly(2))
			->method('handle')
			->willReturnCallback(function (string $property, $value) use (&$callIndex, $expected): void {
				$this->assertSame($expected[$callIndex][0], $property);
				$this->assertSame($expected[$callIndex][1], $value);
				$callIndex++;
			});

		$plugin = $this->getPlugin($fileService);
		$plugin->propFind($propFind, $node);
	}

	private function getPlugin(FileService $fileService): SignatureStatusPlugin {
		\OC::$server->registerService(FileService::class, fn (): FileService => $fileService);
		return new SignatureStatusPlugin();
	}
}
