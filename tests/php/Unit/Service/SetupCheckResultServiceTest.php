<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\SetupCheckResultService;
use OCP\SetupCheck\ISetupCheckManager;
use OCP\SetupCheck\SetupResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SetupCheckResultServiceTest extends TestCase {
	/** @var ISetupCheckManager&MockObject */
	private $checkManager;
	private SetupCheckResultService $service;

	public function setUp(): void {
		$this->checkManager = $this->createMock(ISetupCheckManager::class);
		$this->service = new SetupCheckResultService($this->checkManager);
	}

	/**
	 * @return array<string, array{checkData: array, expectedCount: int, expectedFirstResource: string}>
	 */
	public static function providerGetFormattedChecks(): array {
		return [
			'only_libresign_checks' => [
				'checkData' => [
					'security' => [
						'OCA\\Libresign\\SetupCheck\\JavaSetupCheck' => [
							'severity' => 'success',
							'description' => 'Java OK',
							'link' => 'https://example.com',
						],
					],
					'system' => [
						'OCA\\OtherApp\\SetupCheck\\DatabaseCheck' => [],
					],
				],
				'expectedCount' => 1,
				'expectedFirstResource' => 'Java',
			],
			'mixed_checks' => [
				'checkData' => [
					'system' => [
						'OCA\\Libresign\\SetupCheck\\JSignPdfSetupCheck' => [
							'severity' => 'success',
							'description' => 'JSignPdf OK',
							'link' => 'https://example.com/jsignpdf',
						],
						'OCA\\OtherApp\\SetupCheck\\DatabaseCheck' => [],
						'OCA\\Libresign\\SetupCheck\\ImagickSetupCheck' => [
							'severity' => 'success',
							'description' => 'Imagick OK',
							'link' => 'https://example.com/imagick',
						],
					],
				],
				'expectedCount' => 2,
				'expectedFirstResource' => 'JSignPdf',
			],
		];
	}

	/** @dataProvider providerGetFormattedChecks */
	public function testGetFormattedChecks(array $checkData, int $expectedCount, string $expectedFirstResource): void {
		$checks = $this->buildCheckResults($checkData);
		$this->checkManager->method('runAll')->willReturn($checks);

		$result = $this->service->getFormattedChecks();

		$this->assertCount($expectedCount, $result);
		if ($expectedCount > 0) {
			$this->assertEquals($expectedFirstResource, $result[0]['resource']);
			$this->assertArrayHasKey('category', $result[0]);
		}
	}

	public function testGetLegacyFormattedChecksRemovesCategory(): void {
		$checkData = [
			'system' => [
				'OCA\\Libresign\\SetupCheck\\JavaSetupCheck' => [
					'severity' => 'warning',
					'description' => 'Java Warning',
					'link' => null,
				],
			],
		];
		$checks = $this->buildCheckResults($checkData);
		$this->checkManager->method('runAll')->willReturn($checks);

		$formatted = $this->service->getFormattedChecks();
		$legacyFormatted = $this->service->getLegacyFormattedChecks();

		$this->assertCount(1, $legacyFormatted);
		$this->assertArrayNotHasKey('category', $legacyFormatted[0]);
		$this->assertEquals('info', $legacyFormatted[0]['status']);
		$expected = $formatted[0];
		unset($expected['category']);
		$this->assertEquals($expected, $legacyFormatted[0]);
	}

	/**
	 * @dataProvider providerSeverityMapping
	 */
	public function testSeverityMapping(string $severity, string $expectedStatus): void {
		$checkData = [
			'system' => [
				'OCA\\Libresign\\SetupCheck\\JavaSetupCheck' => [
					'severity' => $severity,
					'description' => 'Message',
					'link' => null,
				],
			],
		];
		$checks = $this->buildCheckResults($checkData);
		$this->checkManager->method('runAll')->willReturn($checks);

		$result = $this->service->getFormattedChecks();

		$this->assertEquals($expectedStatus, $result[0]['status']);
	}

	public static function providerSeverityMapping(): array {
		return [
			'error' => ['error', 'error'],
			'warning' => ['warning', 'info'],
			'success' => ['success', 'success'],
			'unknown' => ['unknown', 'info'],
		];
	}

	/**
	 * Constrói os mocks de SetupResult a partir dos dados fornecidos.
	 *
	 * @param array $checkData Estrutura com os dados das verificações
	 * @return array Array no formato esperado pelo ISetupCheckManager::runAll()
	 */
	private function buildCheckResults(array $checkData): array {
		$checks = [];
		foreach ($checkData as $category => $items) {
			foreach ($items as $checkName => $data) {
				if (!empty($data)) {
					$mockResult = $this->createMock(SetupResult::class);
					$mockResult->method('getSeverity')->willReturn($data['severity']);
					$mockResult->method('getDescription')->willReturn($data['description']);
					$mockResult->method('getLinkToDoc')->willReturn($data['link']);
					$checks[$category][$checkName] = $mockResult;
				} else {
					$mockResult = $this->createMock(SetupResult::class);
					$checks[$category][$checkName] = $mockResult;
				}
			}
		}
		return $checks;
	}
}
