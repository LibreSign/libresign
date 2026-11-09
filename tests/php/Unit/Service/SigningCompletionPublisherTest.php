<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Events\SigningRequestCompletedEvent;
use OCA\Libresign\Service\SignFileService;
use OCP\EventDispatcher\IEventDispatcher;
use PHPUnit\Framework\Attributes\DataProvider;

final class SigningCompletionPublisherTest extends \OCA\Libresign\Tests\Unit\TestCase {
	public static function publishCases(): array {
		return [
			'partial document' => [FileStatus::ABLE_TO_SIGN, FileStatus::PARTIAL_SIGNED, false],
			'completed document' => [FileStatus::PARTIAL_SIGNED, FileStatus::SIGNED, true],
			'already completed document' => [FileStatus::SIGNED, FileStatus::SIGNED, false],
		];
	}

	#[DataProvider('publishCases')]
	public function testPublisherReadsPersistedAggregateBeforeDispatch(FileStatus $previous, FileStatus $persisted, bool $published): void {
		$source = new File();
		$source->setId(33);
		$source->setUuid('aggregate-uuid');
		$source->setStatus(FileStatus::ABLE_TO_SIGN->value);

		$updated = new File();
		$updated->setId(33);
		$updated->setUuid('aggregate-uuid');
		$updated->setStatus($persisted->value);

		$mapper = $this->createMock(FileMapper::class);
		$mapper->expects($this->once())->method('getById')->with(33)->willReturn($updated);
		$dispatcher = $this->createMock(IEventDispatcher::class);
		$dispatcher->expects($published ? $this->once() : $this->never())
			->method('dispatchTyped')
			->with($this->isInstanceOf(SigningRequestCompletedEvent::class));

		$service = (new \ReflectionClass(SignFileService::class))->newInstanceWithoutConstructor();
		$property = new \ReflectionProperty(SignFileService::class, 'fileMapper');
		$property->setValue($service, $mapper);
		$property = new \ReflectionProperty(SignFileService::class, 'eventDispatcher');
		$property->setValue($service, $dispatcher);
		$method = new \ReflectionMethod(SignFileService::class, 'dispatchCompletionIfTransition');
		$method->invoke($service, $source, $previous->value);
	}

	public function testDoesNotReadOrPublishUnpersistedAggregate(): void {
		$source = new File();
		$source->setUuid('unpersisted-uuid');
		$source->setStatus(FileStatus::SIGNED->value);

		$mapper = $this->createMock(FileMapper::class);
		$mapper->expects($this->never())->method('getById');
		$dispatcher = $this->createMock(IEventDispatcher::class);
		$dispatcher->expects($this->never())->method('dispatchTyped');

		$service = (new \ReflectionClass(SignFileService::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(SignFileService::class, 'fileMapper'))->setValue($service, $mapper);
		(new \ReflectionProperty(SignFileService::class, 'eventDispatcher'))->setValue($service, $dispatcher);
		(new \ReflectionMethod(SignFileService::class, 'dispatchCompletionIfTransition'))
			->invoke($service, $source, FileStatus::PARTIAL_SIGNED->value);
	}
}
