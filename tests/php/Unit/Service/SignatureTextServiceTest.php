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
use OCP\IUserSession;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class SignatureTextServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private SignatureTextService $service;
	private IAppConfig $appConfig;
	private IL10N $l10n;
	private IDateTimeZone $dateTimeZone;
	private IRequest&MockObject $request;
	private IUserSession&MockObject $userSession;
	private LoggerInterface&MockObject $logger;


	public function setUp(): void {
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
		$this->appConfig = $this->getMockAppConfig();
		$this->dateTimeZone = \OCP\Server::get(IDateTimeZone::class);
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function getClass(): SignatureTextService {
		$this->service = new SignatureTextService(
			$this->appConfig,
			$this->l10n,
			$this->dateTimeZone,
			$this->request,
			$this->userSession,
			$this->logger,
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
				'{{SignerCommonName}} signed the document on {{ServerSignatureDate}}',
				[
					'SignerCommonName' => 'John Doe',
					'ServerSignatureDate' => '2025-03-31T23:53:47+00:00',
				],
				'John Doe signed the document on 2025-03-31T23:53:47+00:00',
			],
			'plain text with vars and line break' => [
				"{{SignerCommonName}}\nsigned the document on {{ServerSignatureDate}}",
				[
					'SignerCommonName' => 'John Doe',
					'ServerSignatureDate' => '2025-03-31T23:53:47+00:00',
				],
				"John Doe\nsigned the document on 2025-03-31T23:53:47+00:00",
			],
			'b tag' => [
				"<b>{{SignerCommonName}}</b>\nsigned the document on {{ServerSignatureDate}}",
				[
					'SignerCommonName' => 'John Doe',
					'ServerSignatureDate' => '2025-03-31T23:53:47+00:00',
				],
				"John Doe\nsigned the document on 2025-03-31T23:53:47+00:00",
			],
			'p tag' => [
				'<p>{{SignerCommonName}}</p><p>signed the document on {{ServerSignatureDate}}</p>',
				[
					'SignerCommonName' => 'John Doe',
					'ServerSignatureDate' => '2025-03-31T23:53:47+00:00',
				],
				"John Doe\nsigned the document on 2025-03-31T23:53:47+00:00",
			],
			'br and p' => [
				'<p>{{SignerCommonName}}</p><br><p>signed the document on {{ServerSignatureDate}}</p>',
				[
					'SignerCommonName' => 'John Doe',
					'ServerSignatureDate' => '2025-03-31T23:53:47+00:00',
				],
				"John Doe\n\nsigned the document on 2025-03-31T23:53:47+00:00",
			],
		];
	}

	#[DataProvider('providerSplitAndGetLongestHalfLength')]
	public function testSplitAndGetLongestHalfLength(string $text, int $expected): void {
		$class = $this->getClass();
		$actual = self::invokePrivate($class, 'splitAndGetLongestHalfLength', [$text]);
		$this->assertEquals($expected, $actual);
	}

	public static function providerSplitAndGetLongestHalfLength(): array {
		return [
			'empty string' => ['', mb_strlen('')],
			'single character' => ['A', mb_strlen('A')],
			'no spaces' => ['Loremipsumdolorsitamet', mb_strlen('Loremipsumdolorsitamet')],
			'space exactly in the middle' => ['Lorem ipsum', mb_strlen('Lorem')],
			'space after middle' => ['Open source rocks', mb_strlen('source rocks')],
			'space before middle' => ['Free software forever', mb_strlen('software forever')],
			'spaces on edges' => [' Leading and trailing ', mb_strlen('and trailing')],
			'unbalanced halves' => ['Short veryveryverylongword', mb_strlen('veryveryverylongword')],
			'equal halves' => ['One Two', mb_strlen('One')],
			'multiple words' => ['This is a longer string with more words', mb_strlen('This is a longer string')],
			'no possible split (no spaces)' => ['ABCDEFGHIJK', mb_strlen('ABCDEFGHIJK')],
			'only spaces' => ['     ', mb_strlen('')],
			'space at beginning' => [' HelloWorld', mb_strlen('HelloWorld')],
			'space at end' => ['HelloWorld ', mb_strlen('HelloWorld')],
			'two short words' => ['a b', mb_strlen('a')],
			'multibyte with accents' => ['João da Silva', mb_strlen('da Silva')],
			'multibyte at split' => ['José Antônio', mb_strlen('Antônio')],
			'multibyte no spaces' => ['Ñandúçara', mb_strlen('Ñandúçara')],
			'emoji in middle' => ['Smile 😀 always', mb_strlen('Smile 😀')],
			'emoji before space' => ['Good job 👍 team', mb_strlen('Good job 👍')],
			'emoji after space' => ['Team 👏 effort', mb_strlen('Team 👏')],
			'cjk characters with space' => ['漢字 漢字', mb_strlen('漢字')],
			'arabic with space' => ['سلام عليكم', mb_strlen('عليكم')],
			'emoji only' => ['😊 🙃', mb_strlen('😊')],
			'mixed accented and emoji' => ['Renée 💡 Dubois', mb_strlen('Renée 💡')],
			'greek with space' => ['Αθήνα Ελλάδα', mb_strlen('Ελλάδα')],
		];
	}

	#[DataProvider('providerSignerNameImage')]
	public function testSignerNameImageVariants(
		string $text,
		int $width,
		int $height,
		string $align,
		float $scale,
	): void {
		$class = $this->getClass();
		$blob = $class->signerNameImage(
			text: $text,
			width: $width,
			height: $height,
			align: $align,
			scale: $scale
		);

		$image = new Imagick();
		$image->readImageBlob($blob);

		$expectedWidth = (int)($width * $scale);
		$expectedHeight = (int)($height * $scale);

		$this->assertEquals($expectedWidth, $image->getImageWidth());
		$this->assertEquals($expectedHeight, $image->getImageHeight());
	}

	public static function providerSignerNameImage(): array {
		return [
			'center 350x100 scale 5' => ['LibreSign', 350, 100, 'center', 5],
			'left 350x100 scale 4' => ['Secure signature', 350, 100, 'left', 4],
			'right 350x100 scale 3' => ['Verified by LibreCode', 350, 100, 'right', 3],

			'center 175x100 scale 2' => ['Fast & Easy Signing', 175, 100, 'center', 2],
			'left 175x100 scale 1.5' => ['LibreSign Service', 175, 100, 'left', 1.5],
			'right 175x100 scale 1' => ['Electronic Docs', 175, 100, 'right', 1],

			'center 175x50 scale 2.5' => ['Secure ✔️', 175, 50, 'center', 2.5],
			'left 175x50 scale 3' => ['Sign now', 175, 50, 'left',3],
			'right 175x50 scale 4' => ['Signed 🔐', 175, 50, 'right', 4],
		];
	}

	public function testHasFont(): void {
		$this->assertFileExists($fallbackFond = __DIR__ . '/../../../../vendor/mpdf/mpdf/ttfonts/DejaVuSerifCondensed.ttf');
	}
}
