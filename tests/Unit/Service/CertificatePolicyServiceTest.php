<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use bovigo\vfs\vfsStream;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\CertificatePolicyService;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class CertificatePolicyServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {

	private IAppData&MockObject $appData;
	private IURLGenerator&MockObject $urlGenerator;
	private IAppConfig $appConfig;
	private IL10N $l10n;

	public function setUp(): void {
		$this->appData = $this->createMock(IAppData::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->appConfig = $this->getMockAppConfig();
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
	}

	private function getService(): CertificatePolicyService {
		return new CertificatePolicyService(
			$this->appData,
			$this->urlGenerator,
			$this->appConfig,
			$this->l10n
		);
	}

	#[DataProvider('providerUpdateOidWithValidValue')]
	public function testUpdateOidWithValidValue(string $oid): void {
		$result = $this->getService()->updateOid($oid);
		$this->assertEquals($oid, $result);
	}

	public static function providerUpdateOidWithValidValue(): array {
		return [
			['1.2.3.4'],
			['2.5.4.10'],
			['0.9.2342.19200300.100.1.1'],
			[''],
		];
	}

	#[DataProvider('providerUpdateOidWithInvalidValue')]
	public function testUpdateOidWithInvalidValue(string $oid): void {
		$this->expectException(LibresignException::class);
		$this->getService()->updateOid($oid);
	}

	public static function providerUpdateOidWithInvalidValue(): array {
		return [
			['1.2..3'],
			['3.2.1'],
			['1'],
		];
	}

	public function testUpdateOid(): void {
		$service = $this->getService();

		$result = $service->updateOid('1.2.3');
		$this->assertEquals('1.2.3', $result);
		$current = $this->appConfig->getValueString('libresign', 'certificate_policies_oid');
		$this->assertEquals('1.2.3', $current);
		$this->assertEquals('1.2.3', $service->getOid());

		$result = $service->updateOid('');
		$this->assertEquals('', $result);
		$this->assertEquals('', $service->getOid());

		$condition = $this->appConfig->hasKey('libresign', 'certificate_policies_oid');
		$this->assertFalse($condition);
	}

	#[DataProvider('providerGetCps')]
	public function testGetCps(bool $fileExists, string $expected): void {
		$folder = $this->createMock(ISimpleFolder::class);

		if ($fileExists) {
			$file = $this->createMock(ISimpleFile::class);
			$folder->method('getFile')->willReturn($file);
			$this->urlGenerator->method('linkToRouteAbsolute')->willReturn($expected);
		} else {
			$folder->method('getFile')->willThrowException(new NotFoundException());
		}

		$this->appData->method('getFolder')->willReturn($folder);
		$service = $this->getService();
		$this->assertSame($expected, $service->getCps());
	}

	public static function providerGetCps(): array {
		return [
			'file exists' => [true, 'https://example.coop/cps'],
			'file not found' => [false, ''],
		];
	}

	#[DataProvider('providerGetFile')]
	public function testGetFile(bool $exists): void {
		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData->method('getFolder')->willReturn($folder);
		$service = $this->getService();

		if ($exists) {
			$file = $this->createMock(ISimpleFile::class);
			$folder->method('getFile')->with('certificate-policy.pdf')->willReturn($file);
			$this->assertSame($file, $service->getFile());
		} else {
			$folder->method('getFile')->willThrowException(new NotFoundException());
			$this->expectException(NotFoundException::class);
			$service->getFile();
		}
	}

	public static function providerGetFile(): array {
		return [
			'success' => [true],
			'not found' => [false],
		];
	}

	#[DataProvider('providerUpdateFileWithValidPdf')]
	public function testUpdateFileWithValidPdf(string $pdfContent): void {
		vfsStream::setup('uploaded');
		$pdfPath = 'vfs://uploaded/test.pdf';
		file_put_contents($pdfPath, $pdfContent);

		$this->urlGenerator->method('linkToRouteAbsolute')->willReturn('https://example.coop/cps');

		$service = $this->getService();
		$result = $service->updateFile($pdfPath);
		$this->assertSame('https://example.coop/cps', $result);
	}

	public static function providerUpdateFileWithValidPdf(): array {
		return [
			['%PDF-1.0' . "\n" . '%LibreSign Test File' . "\n", '1.0'],
			['%PDF-1.1' . "\n" . '%LibreSign Test File' . "\n", '1.1'],
			['%PDF-1.2' . "\n" . '%LibreSign Test File' . "\n", '1.2'],
			['%PDF-1.3' . "\n" . '%LibreSign Test File' . "\n", '1.3'],
			['%PDF-1.4' . "\n" . '%LibreSign Test File' . "\n", '1.4'],
			['%PDF-1.5' . "\n" . '%LibreSign Test File' . "\n", '1.5'],
			['%PDF-1.6' . "\n" . '%LibreSign Test File' . "\n", '1.6'],
			['%PDF-1.7' . "\n" . '%LibreSign Test File' . "\n", '1.7'],
			['%PDF-2.0' . "\n" . '%LibreSign Test File' . "\n", '2.0'],
		];
	}

	public function testUpdateFileWithInvalidType(): void {
		$tmpFile = tempnam(sys_get_temp_dir(), 'txt');
		file_put_contents($tmpFile, 'just text');

		$service = $this->getService();
		$this->expectException(\Exception::class);
		$service->updateFile($tmpFile);
	}
}
