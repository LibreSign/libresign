<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Listener;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\NodeType;
use OCA\Libresign\Events\SigningRequestCompletedEvent;
use OCA\Libresign\Listener\SignedCopyMailListener;
use OCA\Libresign\Service\SignedCopyMailService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignedCopyMailListenerTest extends TestCase {
	private FileMapper&MockObject $fileMapper;
	private SignedCopyMailService&MockObject $signedCopyMailService;
	private SignedCopyMailListener $listener;

	protected function setUp(): void {
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->signedCopyMailService = $this->createMock(SignedCopyMailService::class);
		$this->listener = new SignedCopyMailListener($this->fileMapper, $this->signedCopyMailService);
	}

	public function testQueuesSignedCopyForCompletedRequest(): void {
		$file = $this->completedFile();
		$this->fileMapper->method('getByUuid')->with('request-uuid')->willReturn($file);
		$this->signedCopyMailService->expects($this->once())->method('queue')->with($file);

		$this->listener->handle($this->completedEvent($file));
	}

	public function testIgnoresUnknownRequest(): void {
		$file = $this->completedFile();
		$this->fileMapper->method('getByUuid')->willThrowException(new DoesNotExistException('missing'));
		$this->signedCopyMailService->expects($this->never())->method('queue');

		$this->listener->handle($this->completedEvent($file));
	}

	private function completedFile(): File {
		$file = new File();
		$file->setId(7);
		$file->setUuid('request-uuid');
		$file->setStatus(FileStatus::SIGNED->value);
		$file->setNodeTypeEnum(NodeType::FILE);
		return $file;
	}

	private function completedEvent(File $file): SigningRequestCompletedEvent {
		$event = SigningRequestCompletedEvent::fromTransition($file, FileStatus::ABLE_TO_SIGN->value);
		$this->assertNotNull($event);
		return $event;
	}
}
