<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OC\SystemConfig;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\InstallService;
use OCA\Libresign\Service\PdfParserService;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 */
final class PdfParseServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/** @var IConfig|MockObject */
	private $config;
	/** @var SystemConfig|MockObject */
	private $systemConfig;
	/** @var InstallService|MockObject */
	private $installService;

	public function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->systemConfig = $this->createMock(SystemConfig::class);
		$this->installService = $this->createMock(InstallService::class);
	}

	private function getService(): PdfParserService {
		return new PdfParserService(
			$this->config,
			$this->systemConfig,
			$this->installService
		);
	}

	public function testGetMetadataWithFail(): void {
		$this->expectException(LibresignException::class);
		$this->config
			->method('getAppValue')
			->willReturnCallback(function ($appid, $key, $default) {
				switch ($key) {
					case 'libresign_cli_path': return '/fake_path/';
				}
			});
		$this->systemConfig
			->method('getValue')
			->willReturnCallback(function ($key, $default) {
				switch ($key) {
					case 'datadirectory': return $default;
				}
			});
		$path = '/fake';
		$this->getService()->getMetadata($path);
	}

	/**
	 * @dataProvider providerGetMetadataWIthSuccess
	 */
	public function testGetMetadataWIthSuccess(string $path, array $expected): void {
		$this->installService = \OC::$server->get(InstallService::class);
		$this->systemConfig = \OC::$server->get(SystemConfig::class);
		$this->config = \OC::$server->get(IConfig::class);
		$actual = $this->getService()->getMetadata($path);
		$this->assertEquals($expected, $actual);
	}

	public function providerGetMetadataWIthSuccess(): array {
		return [
			[
				'/../apps/libresign/tests/fixtures/small_valid.pdf',
				[
					'p' => 1,
					'd' => [
						['w' => 595.276, 'h' => 841.89],
					],
				]
			],
			[
				'/../apps/libresign/tests/fixtures/small_valid-signed.pdf',
				[
					'p' => 1,
					'd' => [
						['w' => 595.276, 'h' => 841.89],
					],
				]
			],
		];
	}
}
