<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service;

use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\EmailSignerElementsService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\EmailToken;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\TokenService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCA\Libresign\Tests\Integration\TestCase;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\Security\IHasher;
use OCP\Server;

/**
 * @group DB
 */
final class EmailSignerElementsServiceTest extends TestCase {
	private Folder $root;

	public function setUp(): void {
		parent::setUp();
		// Shared app-data nodes can outlive filesystem cleanup by an earlier test class.
		// Keep real Nextcloud file operations in a fresh, test-owned user folder.
		$user = $this->createAccount('email-signature-test-' . bin2hex(random_bytes(8)), 'test-password');
		$this->root = Server::get(IRootFolder::class)->getUserFolder($user->getUID())->newFolder('signature-elements');
	}

	public function tearDown(): void {
		try {
			if (isset($this->root)) {
				$this->root->delete();
			}
		} finally {
			parent::tearDown();
		}
	}

	/** @return array{EmailSignerElementsService, SignerElementsService} */
	private function createServices(string $browser, string $uuid, string $email = 'signer@example.com'): array {
		$values = ['libresign-uuid' => $uuid];
		$session = $this->createMock(ISession::class);
		$session->method('getId')->willReturn($browser);
		$session->method('get')->willReturnCallback(static function (string $key) use (&$values): mixed {
			return $values[$key] ?? null;
		});
		$session->method('set')->willReturnCallback(static function (string $key, mixed $value) use (&$values): void {
			$values[$key] = $value;
		});
		$sessionService = new SessionService($session);
		$folders = $this->createMock(FolderService::class);
		$folders->method('getUnauthenticatedFolder')->willReturn($this->root);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$signatures = new SignerElementsService(
			$folders, $sessionService, $this->createMock(IURLGenerator::class),
			$this->createMock(UserElementMapper::class), $this->createMock(SignatureBackgroundService::class),
			$this->createMock(SignatureTextService::class), $this->createMock(FileInputValidator::class),
			$this->createMock(IClientService::class), Server::get(ITimeFactory::class), $l10n,
		);
		$policy = $this->createMock(PolicyService::class);
		$policy->method('resolve')->willReturn((new ResolvedPolicy())->setEffectiveValue(true));
		$identifyService = $this->createMock(IdentifyService::class);
		$identifyService->method('getL10n')->willReturn($l10n);
		$identifyService->method('getHasher')->willReturn(Server::get(IHasher::class));
		$entity = new IdentifyMethod();
		$entity->setIdentifierValue($email);
		$entity->setCode(Server::get(IHasher::class)->hash('123456'));
		$token = new EmailToken($identifyService, $this->createMock(TokenService::class));
		$token->enable();
		$emailMethod = $this->createMock(IIdentifyMethod::class);
		$emailMethod->method('getEntity')->willReturn($entity);
		$emailMethod->method('getSettings')->willReturn(['enabled' => true]);
		$emailMethod->method('getSignatureMethods')->willReturn(['emailToken' => $token]);
		$methods = $this->createMock(IdentifyMethodService::class);
		$methods->method('getIdentifyMethodsFromSignRequestId')->willReturn(['email' => [$emailMethod]]);
		return [new EmailSignerElementsService(
			$policy, $methods, $signatures, $sessionService, $folders, Server::get(IConfig::class), $l10n,
		), $signatures];
	}

	public function testSavedImageCanBeReusedReplacedAndForgottenOnLaterDocuments(): void {
		$request = new SignRequest();
		$request->setId(7);
		[$first, $firstImages] = $this->createServices('browser-a', 'document-a');
		$firstImages->replaceSessionElement('signature', 'original-image');
		$nodeId = $firstImages->getElementsFromSession()[0]->getId();
		$first->saveSignature($first->prepareSignature($request, '123456', [['profileNodeId' => $nodeId]], true));
		[$later, $laterImages] = $this->createServices('browser-b', 'document-b');
		$this->assertSame([], $laterImages->getElementsFromSessionAsArray());
		$later->unlock($request, '123456');
		$this->assertSame('original-image', $laterImages->getElementsFromSession()[0]->getContent());

		$laterImages->replaceSessionElement('signature', 'replacement-image');
		$replacementId = $laterImages->getElementsFromSession()[0]->getId();
		$later->saveSignature($later->prepareSignature($request, '123456', [['profileNodeId' => $replacementId]], true));
		[$third, $thirdImages] = $this->createServices('browser-c', 'document-c');
		$third->unlock($request, '123456');
		$this->assertSame('replacement-image', $thirdImages->getElementsFromSession()[0]->getContent());

		$later->forgetSessionElement($replacementId);
		$laterImages->deleteSignatureElement(null, $laterImages->getElementsFromSession()[0]->getParent()->getName(), $replacementId);
		[$fourth] = $this->createServices('browser-d', 'document-d');
		$this->expectExceptionMessage('No saved signature found');
		$fourth->unlock($request, '123456');
	}

	public function testVerifiedImageIsNotExposedToAnotherBrowserWithTheSameLinkOrAnotherEmail(): void {
		$request = new SignRequest();
		$request->setId(7);
		[$first, $firstImages] = $this->createServices('browser-a', 'document-a');
		$firstImages->replaceSessionElement('signature', 'private-image');
		$nodeId = $firstImages->getElementsFromSession()[0]->getId();
		$first->saveSignature($first->prepareSignature($request, '123456', [['profileNodeId' => $nodeId]], true));
		[$verified, $verifiedImages] = $this->createServices('browser-b', 'document-b');
		$verified->unlock($request, '123456');
		$verifiedId = $verifiedImages->getElementsFromSession()[0]->getId();

		[$unverified, $unverifiedImages] = $this->createServices('browser-c', 'document-b');
		$this->assertSame([], $unverifiedImages->getElementsFromSessionAsArray());
		try {
			$unverifiedImages->getElementFromSessionByNodeId($verifiedId);
			$this->fail('Another browser must not preview a verified image');
		} catch (DoesNotExistException) {
		}
		$unverified->forgetSessionElement($verifiedId);
		$this->assertSame('private-image', $verifiedImages->getElementFromSessionByNodeId($verifiedId)->getContent());

		[$otherEmail] = $this->createServices('browser-d', 'document-c', 'other@example.com');
		$this->assertSame($unverified->getSignerState($request), $otherEmail->getSignerState($request));
		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('No saved signature found');
		$otherEmail->unlock($request, '123456');
	}

	public function testCapturedImageIsSavedAfterSigningEvenIfSessionImageWasRemoved(): void {
		$request = new SignRequest();
		$request->setId(7);
		[$service, $images] = $this->createServices('browser-a', 'document-a');
		$images->replaceSessionElement('signature', 'image-used-to-sign');
		$file = $images->getElementsFromSession()[0];
		[, $workerImages] = $this->createServices('worker', '');
		$this->assertSame([], $workerImages->getElementsFromSession());
		$this->assertSame('image-used-to-sign', $workerImages->getElementsFromSession($images->getSessionStorageId())[0]->getContent());

		$signature = $service->prepareSignature($request, '123456', [['profileNodeId' => (string)$file->getId()]], true);
		$this->assertNotNull($signature);
		$request->setSigned(new \DateTime());
		$file->delete();
		$service->saveSignature($signature);

		[$later, $laterImages] = $this->createServices('browser-b', 'document-b');
		$laterRequest = new SignRequest();
		$laterRequest->setId(8);
		$later->unlock($laterRequest, '123456');
		$this->assertSame('image-used-to-sign', $laterImages->getElementsFromSession()[0]->getContent());
	}
}
