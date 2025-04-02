<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class SignerElementsServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private SignerElementsService $service;
	private FolderService&MockObject $folderService;
	private SessionService&MockObject $sessionService;
	private IURLGenerator&MockObject $urlGenerator;
	private UserElementMapper&MockObject $userElementMapper;
	private SignatureBackgroundService&MockObject $signatureBackgroundService;
	private SignatureTextService&MockObject $signatureTextService;

	public function setUp(): void {
		$this->folderService = $this->createMock(FolderService::class);
		$this->sessionService = $this->createMock(SessionService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->userElementMapper = $this->createMock(UserElementMapper::class);
		$this->signatureBackgroundService = $this->createMock(SignatureBackgroundService::class);
		$this->signatureTextService = $this->createMock(SignatureTextService::class);
	}

	private function getClass(): SignerElementsService {
		$this->service = new SignerElementsService(
			$this->folderService,
			$this->sessionService,
			$this->urlGenerator,
			$this->userElementMapper,
			$this->signatureBackgroundService,
			$this->signatureTextService,
		);
		return $this->service;
	}

	#[DataProvider('providerIsSignElementsAvailable')]
	public function testIsSignElementsAvailable(bool $background, bool $text, bool $expected): void {
		$this->signatureBackgroundService->method('isEnabled')->willReturn($background);
		$this->signatureTextService->method('isEnabled')->willReturn($text);
		$available = $this->getClass()->isSignElementsAvailable();
		$this->assertEquals($expected, $available);
	}

	public static function providerIsSignElementsAvailable(): array {
		return [
			[false, false, false],
			[false, true, true],
			[true, true, true],
			[true, false, true],
		];
	}
}
