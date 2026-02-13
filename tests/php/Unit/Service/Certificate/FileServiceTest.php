<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Certificate;

use bovigo\vfs\vfsStream;
use OCA\Libresign\Enum\CertificateEngineType;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Service\Certificate\FileService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FileServiceTest extends TestCase {
	private FileService $service;
	private CertificateEngineFactory&MockObject $engineFactory;
	private LoggerInterface&MockObject $logger;

	public function setUp(): void {
		$this->engineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->service = new FileService($this->engineFactory, $this->logger);
	}

	#[DataProvider('certificateLoadingScenarios')]
	public function testLoadsCertificateFileByGeneration(
		string $instanceId,
		int $generation,
		CertificateEngineType $engineType,
		string $methodName,
	): void {
		vfsStream::setup('libresign');
		$mockEngine = $this->createMock(IEngineHandler::class);
		$configPath = vfsStream::url('libresign/' . $instanceId . '/' . $generation);

		$this->engineFactory
			->expects($this->once())
			->method('getEngine')
			->with($engineType->getEngineName())
			->willReturn($mockEngine);

		$mockEngine->expects($this->once())
			->method('getConfigPathByParams')
			->with($instanceId, $generation)
			->willReturn($configPath);

		$this->logger
			->expects($this->never())
			->method('debug');

		$result = $this->service->$methodName($instanceId, $generation, $engineType);
		$this->assertEquals('', $result);
	}

	#[DataProvider('engineExceptionScenarios')]
	public function testHandlesEngineExceptionGracefully(
		CertificateEngineType $engineType,
		\Exception $exception,
	): void {
		$this->engineFactory
			->expects($this->once())
			->method('getEngine')
			->with($engineType->getEngineName())
			->willThrowException($exception);

		$this->logger
			->expects($this->once())
			->method('debug')
			->with(
				'Failed to load certificate file',
				$this->callback(function (array $context) use ($engineType): bool {
					return $context['instanceId'] === 'instance-1'
						&& $context['generation'] === 1
						&& $context['engineType'] === $engineType->value
						&& isset($context['error']);
				})
			);

		$result = $this->service->getRootCertificateByGeneration('instance-1', 1, $engineType);
		$this->assertEquals('', $result);
	}

	#[DataProvider('engineTypeScenarios')]
	public function testCorrectlyConvertsEngineTypeToEngineName(
		CertificateEngineType $engineType,
		string $expectedEngineName,
	): void {
		vfsStream::setup('libresign');
		$mockEngine = $this->createMock(IEngineHandler::class);
		$this->engineFactory
			->expects($this->once())
			->method('getEngine')
			->with($expectedEngineName)
			->willReturn($mockEngine);

		$mockEngine
			->expects($this->once())
			->method('getConfigPathByParams')
			->willReturn(vfsStream::url('libresign/config'));

		$this->service->getRootCertificateByGeneration('instance', 1, $engineType);
	}

	public static function certificateLoadingScenarios(): array {
		return [
			'Load root CA for OpenSSL generation 1' => [
				'instanceId' => 'instance-1',
				'generation' => 1,
				'engineType' => CertificateEngineType::OpenSSL,
				'methodName' => 'getRootCertificateByGeneration',
			],
			'Load private key for OpenSSL generation 2' => [
				'instanceId' => 'instance-1',
				'generation' => 2,
				'engineType' => CertificateEngineType::OpenSSL,
				'methodName' => 'getPrivateKeyByGeneration',
			],
			'Load root CA for CFSSL generation 1' => [
				'instanceId' => 'instance-2',
				'generation' => 1,
				'engineType' => CertificateEngineType::CFSSL,
				'methodName' => 'getRootCertificateByGeneration',
			],
			'Load private key for CFSSL generation 3' => [
				'instanceId' => 'instance-2',
				'generation' => 3,
				'engineType' => CertificateEngineType::CFSSL,
				'methodName' => 'getPrivateKeyByGeneration',
			],
			'Load certificate for high generation number' => [
				'instanceId' => 'instance-3',
				'generation' => 5,
				'engineType' => CertificateEngineType::OpenSSL,
				'methodName' => 'getRootCertificateByGeneration',
			],
		];
	}

	public static function engineExceptionScenarios(): array {
		return [
			'Engine throws general exception' => [
				'engineType' => CertificateEngineType::OpenSSL,
				'exception' => new \Exception('Engine initialization failed'),
			],
			'Engine throws runtime exception' => [
				'engineType' => CertificateEngineType::CFSSL,
				'exception' => new \RuntimeException('CFSSL not available'),
			],
		];
	}

	public static function engineTypeScenarios(): array {
		return [
			'OpenSSL engine type converts to correct name' => [
				'engineType' => CertificateEngineType::OpenSSL,
				'expectedEngineName' => 'openssl',
			],
			'CFSSL engine type converts to correct name' => [
				'engineType' => CertificateEngineType::CFSSL,
				'expectedEngineName' => 'cfssl',
			],
		];
	}
}
