<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Api\Controller;

use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Service\CaIdentifierService;
use OCA\Libresign\Service\Crl\CrlService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Tests\Api\ApiTestCase;
use OCP\IAppConfig;
use OCP\ITempManager;
use OCP\L10N\IFactory as IL10NFactory;
use Psr\Log\LoggerInterface;

/**
 * @group DB
 */
final class AccountControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testAccountCreateWithInvalidUuid():void {
		$this->createAccount('username', 'password');

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withRequestBody([
				'email' => 'testuser01@test.coop',
				'password' => 'secret',
				'signPassword' => 'secretToSign'
			])
			->withPath('/api/v1/account/create/1234564789')
			->expectStatus(422);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);
		$this->assertEquals('Invalid UUID', $body['ocs']['data']['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testMeWithoutAuthenticatedUser():void {
		$this->request
			->withPath('/api/v1/account/me')
			->expectStatus(404);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testMeWithAuthenticatedUser():void {
		$this->createAccount('username', 'password');
		$this->request
			->withPath('/api/v1/account/me')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			]);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSignatureGenerateRecreatesMissingUserFilesDirectory(): void {
		$user = $this->createAccount('folderserviceapiuser', 'password');
		$userFilesDirectory = $user->getHome() . '/files';
		$this->removeDirectory($userFilesDirectory);
		$this->overwriteService(Pkcs12Handler::class, $this->getPkcs12HandlerWithStubbedGeneration());

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('folderserviceapiuser:password'),
				'Content-Type' => 'application/json',
			])
			->withRequestBody([
				'signPassword' => 'secretToSign',
			])
			->withPath('/api/v1/account/signature')
			->assertResponseCode(200);

		$this->assertRequest();
		self::assertFileExists($userFilesDirectory . '/LibreSign/signature.pfx');
	}

	private function getPkcs12HandlerWithStubbedGeneration(): Pkcs12Handler {
		$handler = $this->getMockBuilder(Pkcs12Handler::class)
			->setConstructorArgs([
				\OCP\Server::get(FolderService::class),
				\OCP\Server::get(IAppConfig::class),
				\OCP\Server::get(CertificateEngineFactory::class),
				\OCP\Server::get(IL10NFactory::class)->get('libresign'),
				\OCP\Server::get(FooterHandler::class),
				\OCP\Server::get(ITempManager::class),
				\OCP\Server::get(LoggerInterface::class),
				\OCP\Server::get(CaIdentifierService::class),
				\OCP\Server::get(DocMdpHandler::class),
				\OCP\Server::get(CrlService::class),
			])
			->onlyMethods(['generateCertificate'])
			->getMock();

		$handler->method('generateCertificate')->willReturn('test pfx content');
		return $handler;
	}

	private function removeDirectory(string $path): void {
		if (!is_dir($path)) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($iterator as $item) {
			if ($item->isDir()) {
				@rmdir($item->getPathname());
				continue;
			}

			@unlink($item->getPathname());
		}

		@rmdir($path);
	}
}
