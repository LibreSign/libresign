<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Listener;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Listener\SignedCallbackListener;
use OCA\Libresign\Service\SignFileService;
use OCP\EventDispatcher\Event;
use OCP\Files\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SignedCallbackListenerTest extends TestCase {
	public static function completionStatuses(): array {
		return [
			'ready to sign' => [FileStatus::ABLE_TO_SIGN, false],
			'partially signed' => [FileStatus::PARTIAL_SIGNED, false],
			'completed' => [FileStatus::SIGNED, true],
		];
	}

	#[DataProvider('completionStatuses')]
	public function testLegacyCallbackOnlyFiresOnDocumentCompletion(FileStatus $status, bool $expected): void {
		$document = new FileEntity();
		$document->setStatus($status->value);
		$signedFile = $this->createMock(File::class);
		$event = $this->createMock(SignedEvent::class);
		$event->method('getLibreSignFile')->willReturn($document);
		$event->method('getSignedFile')->willReturn($signedFile);

		$service = $this->createMock(SignFileService::class);
		$service->expects($expected ? $this->once() : $this->never())
			->method('notifyCallback')
			->with($signedFile);

		(new SignedCallbackListener($service))->handle($event);
	}

	public function testIgnoresUnrelatedEvents(): void {
		$service = $this->createMock(SignFileService::class);
		$service->expects($this->never())->method('notifyCallback');

		(new SignedCallbackListener($service))->handle(new Event());
	}
}
