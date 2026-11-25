<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing\External;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\Signing\External\AuthorizedRequest;
use OCA\Libresign\Service\Signing\External\DocumentRevision;
use OCA\Libresign\Service\Signing\External\Publication;
use OCA\Libresign\Service\Signing\External\PublicationContext;
use OCA\Libresign\Service\Signing\External\PublicationNotifications;
use OCA\Libresign\Service\Signing\External\PublicationPreparation;
use OCA\Libresign\Service\Signing\External\PublicationTransaction;
use OCP\Files\File as NextcloudFile;
use PHPUnit\Framework\TestCase;

final class PublicationTest extends TestCase {
	/** @return array{AuthorizedRequest,PublicationContext} */
	private function context(): array {
		$file = new FileEntity();
		$request = new SignRequest();
		$request->setId(42);
		$authorized = new AuthorizedRequest($file, $request, 'sign-request:42');
		$source = $this->createMock(NextcloudFile::class);
		$output = $this->createMock(NextcloudFile::class);
		$output->method('getId')->willReturn(123);
		$context = new PublicationContext(
			$authorized, 2, $file, new DocumentRevision($source, '%PDF-1.7', 'etag-1'),
			$output, str_repeat('a', 64), 'synthetic-certificate', 'session-id',
		);
		return [$authorized, $context];
	}

	public function testNotificationsRunOnlyAfterCommittedPublication(): void {
		[$authorized, $context] = $this->context();
		$steps = [];
		$preparer = $this->createMock(PublicationPreparation::class);
		$preparer->expects($this->once())->method('prepare')
			->with($authorized, 'session-id', 'synthetic-certificate')
			->willReturnCallback(static function () use ($context, &$steps): PublicationContext {
				$steps[] = 'prepared';
				return $context;
			});
		$committer = $this->createMock(PublicationTransaction::class);
		$committer->expects($this->once())->method('commit')
			->with($context)
			->willReturnCallback(static function () use (&$steps, $context): array {
				$steps[] = 'committed';
				return ['file' => $context->file, 'activated' => [], 'previousStatus' => 1];
			});
		$events = $this->createMock(PublicationNotifications::class);
		$events->expects($this->once())->method('dispatch')
			->with($context->file, $authorized->signRequest, $context->output, 1, [])
			->willReturnCallback(static function () use (&$steps): void {
				$steps[] = 'notified';
			});
		$result = (new Publication($preparer, $committer, $events))
			->publish($authorized, 'session-id', 'synthetic-certificate');
		self::assertSame(['prepared', 'committed', 'notified'], $steps);
		self::assertSame(['status' => 'completed', 'sessionId' => 'session-id',
			'signedNodeId' => 123, 'signedHash' => str_repeat('a', 64)], $result);
	}

	public function testTransactionFailureNeverRunsNotifications(): void {
		[$authorized, $context] = $this->context();
		$preparer = $this->createMock(PublicationPreparation::class);
		$preparer->method('prepare')->willReturn($context);
		$committer = $this->createMock(PublicationTransaction::class);
		$committer->method('commit')->willThrowException(new \DomainException('Publication conflict'));
		$events = $this->createMock(PublicationNotifications::class);
		$events->expects($this->never())->method('dispatch');
		$this->expectException(\DomainException::class);
		(new Publication($preparer, $committer, $events))
			->publish($authorized, 'session-id', 'synthetic-certificate');
	}

	public function testPreparationFailureDoesNotStartDatabaseWork(): void {
		[$authorized] = $this->context();
		$preparer = $this->createMock(PublicationPreparation::class);
		$preparer->method('prepare')->willThrowException(new \DomainException('Invalid PDF source'));
		$committer = $this->createMock(PublicationTransaction::class);
		$committer->expects($this->never())->method('commit');
		$events = $this->createMock(PublicationNotifications::class);
		$events->expects($this->never())->method('dispatch');
		$this->expectException(\DomainException::class);
		(new Publication($preparer, $committer, $events))
			->publish($authorized, 'session-id', 'synthetic-certificate');
	}
}
