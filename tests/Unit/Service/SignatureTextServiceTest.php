<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\SignatureTextService;
use OCP\IAppConfig;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IRequest;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class SignatureTextServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private SignatureTextService $service;
	private IAppConfig $appConfig;
	private IL10N $l10n;
	private IDateTimeZone $dateTimeZone;
	private IRequest&MockObject $request;

	public function setUp(): void {
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
		$this->appConfig = $this->getMockAppConfig();
		$this->dateTimeZone = \OCP\Server::get(IDateTimeZone::class);
		$this->request = $this->createMock(IRequest::class);
	}


	private function getClass(): SignatureTextService {
		$this->service = new SignatureTextService(
			$this->appConfig,
			$this->l10n,
			$this->dateTimeZone,
			$this->request,
		);
		return $this->service;
	}

	public function testCollectingMetadata(): void {
		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueBool(Application::APP_ID, 'collect_metadata', true);

		$actual = $this->getClass()->getAvailableVariables();
		$this->assertArrayHasKey('{{SignerIP}}', $actual);

		$template = $this->getClass()->getDefaultTemplate();
		$this->assertStringContainsString('IP', $template);
	}

	public function testNotCollectingMetadata(): void {
		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueBool(Application::APP_ID, 'collect_metadata', false);

		$actual = $this->getClass()->getAvailableVariables();
		$this->assertArrayNotHasKey('{{SignerIP}}', $actual);

		$template = $this->getClass()->getDefaultTemplate();
		$this->assertStringNotContainsString('IP', $template);
	}

	#[DataProvider('providerSave')]
	public function testSave($template, $context, $parsed): void {
		$fromSave = $this->getClass()->save($template);
		$fromParse = $this->getClass()->parse($fromSave['template'], $context);
		$this->assertEquals($parsed, $fromParse['parsed']);
	}

	public static function providerSave(): array {
		return [
			'empty' => ['', [], ''],
			'without vars' => ['Just a static text.', [], 'Just a static text.',],
			'plain text with vars' => [
				'{{SignerName}} signed the document on {{ServerSignatureDate}}',
				[
					'SignerName' => 'John Doe',
					'ServerSignatureDate' => '2025-03-31T23:53:47+00:00',
				],
				'John Doe signed the document on 2025-03-31T23:53:47+00:00',
			],
			'plain text with vars and line break' => [
				"{{SignerName}}\nsigned the document on {{ServerSignatureDate}}",
				[
					'SignerName' => 'John Doe',
					'ServerSignatureDate' => '2025-03-31T23:53:47+00:00',
				],
				"John Doe\nsigned the document on 2025-03-31T23:53:47+00:00",
			],
			'b tag' => [
				"<b>{{SignerName}}</b>\nsigned the document on {{ServerSignatureDate}}",
				[
					'SignerName' => 'John Doe',
					'ServerSignatureDate' => '2025-03-31T23:53:47+00:00',
				],
				"John Doe\nsigned the document on 2025-03-31T23:53:47+00:00",
			],
			'p tag' => [
				'<p>{{SignerName}}</p><p>signed the document on {{ServerSignatureDate}}</p>',
				[
					'SignerName' => 'John Doe',
					'ServerSignatureDate' => '2025-03-31T23:53:47+00:00',
				],
				"John Doe\nsigned the document on 2025-03-31T23:53:47+00:00",
			],
			'br and p' => [
				'<p>{{SignerName}}</p><br><p>signed the document on {{ServerSignatureDate}}</p>',
				[
					'SignerName' => 'John Doe',
					'ServerSignatureDate' => '2025-03-31T23:53:47+00:00',
				],
				"John Doe\n\nsigned the document on 2025-03-31T23:53:47+00:00",
			],
		];
	}
}
