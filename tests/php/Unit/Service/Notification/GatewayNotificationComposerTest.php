<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Notification;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\Notification\GatewayNotificationComposer;
use OCA\Libresign\Service\Notification\NotificationHistoryService;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GatewayNotificationComposerTest extends TestCase {
	#[DataProvider('eventCases')]
	public function testWhatsAppBusinessContractIsEventSpecificAndOrdered(
		string $event, bool $first, string $expectedFragment, string $route,
	): void {
		$request = new SignRequest();
		$request->setId(42);
		$request->setUuid('synthetic-request');
		$request->setDescription('Synthetic description');
		$request->setDisplayName('Synthetic signer');
		$file = new File();
		$file->setName('Example.pdf');
		$file->setUuid('synthetic-file');
		$stored = new SignRequest();
		$stored->setMetadata($first ? [] : ['notify' => [['method' => 'whatsappbusiness']]]);
		$mapper = $this->createMock(SignRequestMapper::class);
		$mapper->method('getByIdUncached')->willReturn($stored);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string
			=> $params === [] ? $text : vsprintf($text, $params));
		$url = $this->createMock(IURLGenerator::class);
		$url->method('linkToRouteAbsolute')->willReturnCallback(
			static fn (string $name, array $params): string => 'https://example.invalid/' . $name . '/' . $params['uuid'],
		);
		$composer = new GatewayNotificationComposer(new NotificationHistoryService($mapper), $l10n, $url);
		$payload = $composer->composePayload($event, $request, $file, 'whatsappbusiness', 'whatsappbusiness');
		$this->assertSame(2, $payload->extra['expected_body_parameter_count']);
		$this->assertCount(2, $payload->extra['body_parameters']);
		$this->assertStringContainsString($expectedFragment, $payload->extra['body_parameters'][0]);
		$this->assertStringContainsString('Example.pdf', $payload->extra['body_parameters'][0]);
		$this->assertStringNotContainsString('https://', $payload->extra['body_parameters'][0]);
		$this->assertStringContainsString($route, $payload->extra['body_parameters'][1]);
		$this->assertStringNotContainsString("\n", $payload->extra['body_parameters'][0]);
		$this->assertStringNotContainsString("\t", $payload->extra['body_parameters'][0]);
		$this->assertStringContainsString("\n", $payload->body);
		$plain = $composer->compose($event, $request, $file, 'whatsappbusiness');
		$this->assertSame($plain, $payload->body);
		$sms = $composer->composePayload($event, $request, $file, 'sms', 'sms');
		$this->assertSame([], $sms->extra);
		$this->assertSame($composer->compose($event, $request, $file, 'sms'), $sms->body);
	}

	public static function eventCases(): array {
		return [
			'initial request' => ['sign_request', true, 'There is a document', 'libresign.page.sign'],
			'updated request' => ['sign_request', false, 'Changes have been made', 'libresign.page.sign'],
			'signed file' => ['file_signed', true, 'Synthetic signer signed', 'libresign.page.validationFilePublic'],
		];
	}
}
