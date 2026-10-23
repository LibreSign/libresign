<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service;

use OCA\Libresign\Db\File as LibresignFile;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Handler\SignEngine\JSignPdf\JSignPdfHandler;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Service\CaIdentifierService;
use OCA\Libresign\Service\Crl\CrlService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\PfxProvider;
use OCA\Libresign\Service\Signature\PdfSignatureValidationService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Tests\Integration\AppDataTestCase;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\ITempManager;
use OCP\IUser;
use OCP\L10N\IFactory as IL10NFactory;
use OCP\Server;
use Psr\Log\LoggerInterface;

/**
 * @group DB
 */
final class SignFileServiceTemporaryImageTest extends AppDataTestCase {
	private bool $cleanupTriggered = false;
	private bool $imageReachedSigner = false;

	public function testSigningRestoresImageAfterTemporaryCleanup(): void {
		$fixture = $this->createVisibleSignatureRequest();
		$this->cleanTemporaryFilesDuringCertificatePreparation();
		$this->captureVisibleImageAtSigningBoundary($fixture['content']);

		$service = Server::get(SignFileService::class);
		$service->setLibreSignFile($fixture['file'])
			->setSignRequest($fixture['request'])
			->setCurrentUser($fixture['user'])
			->setUserUniqueIdentifier('account:' . $fixture['user']->getUID())
			->setSignWithoutPassword()
			->setVisibleElements([[
				'documentElementId' => $fixture['elementId'],
				'profileNodeId' => $fixture['imageId'],
			]]);

		try {
			$service->sign();
		} catch (\LogicException) {
			// The fake signer stops after checking the PNG, before writing a PDF.
		}

		$this->assertTrue($this->cleanupTriggered);
		$this->assertTrue($this->imageReachedSigner);
	}

	/** @return array{file:LibresignFile,request:SignRequest,user:IUser,elementId:int,imageId:int,content:string} */
	private function createVisibleSignatureRequest(): array {
		$user = $this->createAccount('temporary-image-signer', 'test-password');
		$png = (string)base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==');
		$image = Server::get(IRootFolder::class)->getUserFolder($user->getUID())->newFile('signature.png', $png);
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/pdfs/small_valid.pdf'))],
			'name' => 'Synthetic signature test',
			'signers' => [[
				'identifyMethods' => [[
					'method' => 'account',
					'mandatory' => 0,
					'value' => $user->getUID(),
				]],
			]],
			'userManager' => $user,
		]);
		$request = $this->getSignersFromFileId($file->getId())[0];
		$element = new FileElement();
		$element->setFileId($file->getId());
		$element->setSignRequestId($request->getId());
		$element->setType('signature');
		$element->setPage(1);
		$element->setLlx(10);
		$element->setLly(20);
		$element->setUrx(150);
		$element->setUry(80);
		$element->setCreatedAt(new \DateTime());
		$element = Server::get(FileElementMapper::class)->insert($element);

		return [
			'file' => $file,
			'request' => $request,
			'user' => $user,
			'elementId' => $element->getId(),
			'imageId' => $image->getId(),
			'content' => $png,
		];
	}

	private function cleanTemporaryFilesDuringCertificatePreparation(): void {
		$tempManager = Server::get(ITempManager::class);
		$provider = $this->createMock(PfxProvider::class);
		$provider->method('getOrGeneratePfx')->willReturnCallback(
			function () use ($tempManager): array {
				$tempManager->clean();
				$this->cleanupTriggered = true;
				return ['pfx' => 'synthetic certificate', 'password' => null];
			},
		);
		$this->overwriteService(PfxProvider::class, $provider);
	}

	private function captureVisibleImageAtSigningBoundary(string $expectedImage): void {
		// The integration boundary is PNG availability; cryptographic signing is covered separately.
		$certificateEngine = $this->createMock(IEngineHandler::class);
		$certificateEngine->method('readCertificate')->willReturn(['subject' => ['CN' => 'Test signer']]);
		$certificateFactory = $this->createMock(CertificateEngineFactory::class);
		$certificateFactory->method('getEngine')->willReturn($certificateEngine);
		$this->overwriteService(CertificateEngineFactory::class, $certificateFactory);
		$this->overwriteService(Pkcs12Handler::class, new Pkcs12Handler(
			Server::get(FolderService::class),
			Server::get(IAppConfig::class),
			$certificateFactory,
			Server::get(IL10NFactory::class)->get('libresign'),
			Server::get(FooterHandler::class),
			Server::get(LoggerInterface::class),
			Server::get(CaIdentifierService::class),
			Server::get(DocMdpHandler::class),
			Server::get(CrlService::class),
			Server::get(PdfSignatureValidationService::class),
		));

		$signer = $this->getMockBuilder(JSignPdfHandler::class)
			->disableOriginalConstructor()
			->onlyMethods(['getSignedContent'])
			->getMock();
		$signer->method('getSignedContent')->willReturnCallback(function () use ($signer, $expectedImage): string {
			$visibleElements = $signer->getVisibleElements();
			$this->assertCount(1, $visibleElements);
			$path = reset($visibleElements)->getTempFile();
			$this->assertFileExists($path);
			$this->assertSame($expectedImage, file_get_contents($path));
			$this->imageReachedSigner = true;
			throw new \LogicException();
		});
		$this->overwriteService(JSignPdfHandler::class, $signer);
	}
}
