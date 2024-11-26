<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
	private IRequest|MockObject $request;
	private SignFileService $signFileService;
	private IL10N $l10n;
	private IUserSession $userSession;
	private MockController $controller;

	public function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->mockAppConfig([
			'identify_methods' => [
				[
					'name' => 'email',
					'enabled' => 1,
				],
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
		$this->getExpectedExceptionCode(404);
		$this->expectExceptionMessage(json_encode([
			'action' => 2000,
			'errors' => ['Invalid UUID'],
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
		$this->getExpectedExceptionCode(404);
		$this->expectExceptionMessage(json_encode([
			'action' => 2000,
			'errors' => ['Invalid UUID'],
		]));

		$this->controller->validateSignRequestUuid($file->getUuid());
	}
}
