<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignRequest;

use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\SignRequest\SignerLanguageService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\IL10N;
use OCP\L10N\IFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class SignerLanguageServiceTest extends TestCase {
	private IFactory&MockObject $l10nFactory;
	private SignRequestMapper&MockObject $signRequestMapper;
	private SignerLanguageService $service;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->l10nFactory
			->method('languageExists')
			->willReturnCallback(static fn (string $app, string $language): bool => $app === 'libresign' && in_array($language, ['en', 'de', 'pt_BR'], true));
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->service = new SignerLanguageService($this->l10nFactory, $this->signRequestMapper);
	}

	public function testGetAvailableLanguagesListsTheAppTranslations(): void {
		$this->l10nFactory
			->method('findAvailableLanguages')
			->with('libresign')
			->willReturn(['en', 'pt_BR', 'de', 'de']);

		$this->assertSame(['de', 'en', 'pt_BR'], $this->service->getAvailableLanguages());
	}

	#[DataProvider('storedLanguageCases')]
	public function testGetLanguageReadsTheStoredChoice(?array $metadata, ?string $expected): void {
		$signRequest = new SignRequest();
		if ($metadata !== null) {
			$signRequest->setMetadata($metadata);
		}

		$this->assertSame($expected, $this->service->getLanguage($signRequest));
	}

	public static function storedLanguageCases(): array {
		return [
			'no metadata' => [null, null],
			'no choice' => [['deviceGeolocationRequirement' => 'disabled'], null],
			'available language' => [['language' => 'pt_BR'], 'pt_BR'],
			'language no longer available' => [['language' => 'xx'], null],
			'empty value' => [['language' => ''], null],
			'not a string' => [['language' => ['de']], null],
		];
	}

	public function testGetL10nUsesTheStoredLanguage(): void {
		$signRequest = new SignRequest();
		$signRequest->setMetadata(['language' => 'de']);
		$l10n = $this->createMock(IL10N::class);
		$this->l10nFactory
			->expects($this->once())
			->method('get')
			->with('libresign', 'de')
			->willReturn($l10n);

		$this->assertSame($l10n, $this->service->getL10n($signRequest));
	}

	public function testGetL10nKeepsTheDefaultWithoutChoice(): void {
		$this->l10nFactory->expects($this->never())->method('get');

		$this->assertNull($this->service->getL10n(new SignRequest()));
	}

	public function testSetLanguageStoresTheChoiceAndKeepsOtherMetadata(): void {
		$signRequest = new SignRequest();
		$signRequest->setMetadata(['deviceGeolocationRequirement' => 'required']);
		$this->signRequestMapper->expects($this->once())->method('update')->with($signRequest);

		$this->service->setLanguage($signRequest, 'de');

		$this->assertSame(
			['deviceGeolocationRequirement' => 'required', 'language' => 'de'],
			$signRequest->getMetadata(),
		);
	}

	public function testSetLanguageWithEmptyValueClearsTheChoice(): void {
		$signRequest = new SignRequest();
		$signRequest->setMetadata(['language' => 'de', 'deviceGeolocationRequirement' => 'required']);
		$this->signRequestMapper->expects($this->once())->method('update')->with($signRequest);

		$this->service->setLanguage($signRequest, '');

		$this->assertSame(['deviceGeolocationRequirement' => 'required'], $signRequest->getMetadata());
	}

	#[DataProvider('unchangedLanguageCases')]
	public function testSetLanguageSkipsTheWriteWhenNothingChanges(?array $metadata, string $language): void {
		$signRequest = new SignRequest();
		if ($metadata !== null) {
			$signRequest->setMetadata($metadata);
		}
		$this->signRequestMapper->expects($this->never())->method('update');

		$this->service->setLanguage($signRequest, $language);
	}

	public static function unchangedLanguageCases(): array {
		return [
			'same language' => [['language' => 'de'], 'de'],
			'clear without choice' => [null, ''],
		];
	}
}
