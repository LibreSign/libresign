<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit;

use OCA\Files_Trashbin\Trash\ITrashManager;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Controller\AEnvironmentPageAwareController;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\SignFileService;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\MockObject\MockObject;

class MockController extends AEnvironmentPageAwareController {
}

/**
 * @group DB
 */
final class AEnvironmentPageAwareControllerTest extends TestCase {
	private IRequest&MockObject $request;
	private SignFileService $signFileService;
	private IL10N $l10n;
	private IUserSession $userSession;
	private MockController $controller;

	public function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->getMockAppConfig()->setValueArray(Application::APP_ID, 'identify_methods', [
			[
				'name' => 'email',
				'enabled' => 1,
			],
		]);
		$this->signFileService = \OCP\Server::get(SignFileService::class);
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
		$this->userSession = \OCP\Server::get(IUserSession::class);

		$this->controller = new MockController(
			$this->request,
			$this->signFileService,
			$this->l10n,
			$this->userSession,
		);
		parent::setUp();
	}

	public function testLoadFileUuidWithEmptyUuid(): void {
		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(404);
		$this->expectExceptionMessage(json_encode([
			'action' => 2000,
			'errors' => [['message' => 'Invalid UUID']],
		]));
		$this->controller->loadNextcloudFileFromSignRequestUuid('');
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testLoadFileUuidWhenFileNotFound(): void {
		$user = $this->createAccount('username', 'password');
		$user->setEMailAddress('person@test.coop');
		$file = $this->requestSignFile([
			'file' => ['base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))],
			'name' => 'test',
			'users' => [
				[
					'identify' => [
						'account' => 'username',
					],
				],
			],
			'userManager' => $user,
		]);

		$this->userSession->setUser($user);

		$root = \OCP\Server::get(IRootFolder::class);
		$nextcloudFile = $root->getById($file->getNodeId());
		$trashManager = \OCP\Server::get(ITrashManager::class);
		$trashManager->pauseTrash();
		$nextcloudFile[0]->delete();

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(404);
		$this->expectExceptionMessage(json_encode([
			'action' => 2000,
			'errors' => [['message' => 'Invalid UUID']],
		]));

		$this->controller->validateSignRequestUuid($file->getUuid());
	}
}
