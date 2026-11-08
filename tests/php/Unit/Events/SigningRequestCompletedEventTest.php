<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Events;

use OCA\Libresign\Db\File;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Events\SigningRequestCompletedEvent;
use OCP\EventDispatcher\IWebhookCompatibleEvent;
use PHPUnit\Framework\Attributes\DataProvider;

final class SigningRequestCompletedEventTest extends \OCA\Libresign\Tests\Unit\TestCase {
    public static function transitionCases(): array {
        return [
            'partial document' => [false, FileStatus::ABLE_TO_SIGN, FileStatus::PARTIAL_SIGNED, false],
            'still open' => [false, FileStatus::DRAFT, FileStatus::ABLE_TO_SIGN, false],
            'duplicate signed' => [false, FileStatus::SIGNED, FileStatus::SIGNED, false],
            'document completed' => [false, FileStatus::PARTIAL_SIGNED, FileStatus::SIGNED, true],
            'envelope completed' => [true, FileStatus::PARTIAL_SIGNED, FileStatus::SIGNED, true],
        ];
    }

    #[DataProvider('transitionCases')]
    public function testOnlyCompletionTransitionsArePublic(bool $envelope, FileStatus $previous, FileStatus $current, bool $expected): void {
        $file = new File();
        $file->setUuid('test-request-id');
        $file->setNodeType($envelope ? 'envelope' : 'file');
        $file->setStatus($current->value);

        $event = SigningRequestCompletedEvent::fromTransition($file, $previous->value);
        $this->assertSame($expected, $event !== null);
        if ($event === null) {
            return;
        }
        $this->assertInstanceOf(IWebhookCompatibleEvent::class, $event);
        $payload = $event->getWebhookSerializable();
        $this->assertSame(1, $payload['contractVersion']);
        $this->assertSame('test-request-id', $payload['requestUuid']);
        $this->assertSame($envelope ? 'envelope' : 'document', $payload['requestType']);
        $this->assertSame('completed', $payload['status']);
        $this->assertSame('libresign.workflow.completed.v1:test-request-id', $payload['eventId']);
        $this->assertSame(['contractVersion', 'eventId', 'requestUuid', 'requestType', 'status'], array_keys($payload));
        $this->assertSame($payload, json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
    }
}
