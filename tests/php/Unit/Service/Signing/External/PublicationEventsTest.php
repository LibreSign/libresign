<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing\External;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Events\SignedEventFactory;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\SequentialSigningService;
use OCA\Libresign\Service\Signing\External\PublicationEvents;
use OCA\Libresign\Service\Webhook\NativeWebhookEventPublisher;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File as NextcloudFile;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class PublicationEventsTest extends TestCase {
	public function testFailuresAfterCommitDoNotSuppressOtherDeliveryChannelsOrExposeSecrets(): void {
		$file = new FileEntity();
		$request = new SignRequest();
		$request->setId(123);
		$output = $this->createMock(NextcloudFile::class);
		$statuses = $this->createMock(FileStatusService::class);
		$statuses->method('onQueuedJobCommitted')->willThrowException(new \RuntimeException('Bearer private-secret'));
		$requests = $this->createMock(SignRequestMapper::class);
		$requests->expects($this->once())->method('invalidateEntityCache')->with($request);
		$sequential = $this->createMock(SequentialSigningService::class);
		$sequential->expects($this->once())->method('notifyActivatedSigners')->with([]);
		$factory = $this->createMock(SignedEventFactory::class);
		$factory->method('make')->willThrowException(new \RuntimeException('Bearer private-secret'));
		$dispatcher = $this->createMock(IEventDispatcher::class);
		$dispatcher->expects($this->never())->method('dispatchTyped');
		$webhooks = $this->createMock(NativeWebhookEventPublisher::class);
		$webhooks->expects($this->once())->method('signerSigned')->with($file, $request);
		$webhooks->expects($this->once())->method('requestCompleted')->with($file, 1);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->exactly(2))->method('error')->with(
			'CSC post-commit action failed',
			$this->callback(static function (array $context): bool {
				$text = json_encode($context, JSON_THROW_ON_ERROR);
				return !str_contains($text, 'private-secret')
					&& !isset($context['exception'])
					&& isset($context['exceptionClass'], $context['step'])
					&& $context['signRequestId'] === 123;
			}),
		);
		(new PublicationEvents($statuses, $requests, $sequential, $factory, $dispatcher, $webhooks, $logger))
			->dispatch($file, $request, $output, 1, []);
	}
}
