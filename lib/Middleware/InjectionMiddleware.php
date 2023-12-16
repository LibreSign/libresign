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

namespace OCA\Libresign\Middleware;

use OC\AppFramework\Http as AppFrameworkHttp;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Controller\AEnvironmentAwareController;
use OCA\Libresign\Controller\AEnvironmentPageAwareController;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Exception\PageException;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\Attribute\RequireManager;
use OCA\Libresign\Middleware\Attribute\RequireSigner;
use OCA\Libresign\Middleware\Attribute\RequireSignRequestUuid;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Util;

class InjectionMiddleware extends Middleware {
	protected ?string $userId;

	public function __construct(
		private IRequest $request,
		private IUserSession $userSession,
		private ValidateHelper $validateHelper,
		private SignRequestMapper $signRequestMapper,
		private FileMapper $fileMapper,
		private IInitialState $initialState,
		private SignFileService $signFileService,
		private IL10N $l10n,
		?string $userId,
	) {
		$this->request = $request;
		$this->userId = $userId;
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @throws \Exception
	 */
	public function beforeController(Controller $controller, string $methodName) {
		switch (true) {
			case $controller instanceof AEnvironmentAwareController:
				$apiVersion = $this->request->getParam('apiVersion');
				/** @var AEnvironmentAwareController $controller */
				$controller->setAPIVersion((int) substr($apiVersion, 1));
				break;
			case $controller instanceof AEnvironmentPageAwareController:
				break;
			default:
				return;
		}

		$reflectionMethod = new \ReflectionMethod($controller, $methodName);

		if (!empty($reflectionMethod->getAttributes(RequireManager::class))) {
			$this->getLoggedIn();
		}

		if (!empty($reflectionMethod->getAttributes(RequireSigner::class))) {
			$this->requireSigner();
		}

		if (!empty($reflectionMethod->getAttributes(RequireSignRequestUuid::class))
			&& $controller instanceof AEnvironmentPageAwareController
		) {
			/** @var AEnvironmentPageAwareController $controller */
			$controller->loadSignRequestUuid(
				uuid: $this->request->getParam('uuid'),
			);
		}
	}

	private function getLoggedIn(): void {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			throw new \Exception($this->l10n->t('You are not allowed to request signing'), Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		$this->validateHelper->canRequestSign($user);
	}

	private function requireSigner(): void {
		$uuid = $this->request->getParam('uuid');

		try {
			$user = $this->userSession->getUser();
			$this->validateHelper->validateSigner($uuid, $user);
		} catch (LibresignException $e) {
			throw new LibresignException($e->getMessage());
		}
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @param \Exception $exception
	 * @throws \Exception
	 * @return Response
	 */
	public function afterException($controller, $methodName, \Exception $exception): Response {
		switch (true) {
			case $exception instanceof LibresignException:
				if ($this->isJson($exception->getMessage())) {
					$body = json_decode($exception->getMessage());
				} else {
					$body = [
						'message' => $exception->getMessage(),
					];
				}
				return new JSONResponse(
					$body,
					$exception->getCode() === 0
						? AppFrameworkHttp::STATUS_UNPROCESSABLE_ENTITY
						: $exception->getCode()
				);
			case $exception instanceof PageException:
				if ($this->isJson($exception->getMessage())) {
					$this->initialState->provideInitialState('config', json_decode($exception->getMessage(), true));
				} else {
					$this->initialState->provideInitialState('error', ['message' => $exception->getMessage()]);
				}

				Util::addScript(Application::APP_ID, 'libresign-external');
				$response = new TemplateResponse(
					Application::APP_ID,
					'external',
					[],
					TemplateResponse::RENDER_AS_BASE,
					$exception->getCode() === 0
						? AppFrameworkHttp::STATUS_UNPROCESSABLE_ENTITY
						: $exception->getCode()
				);

				$policy = new ContentSecurityPolicy();
				$policy->addAllowedFrameDomain('\'self\'');
				$response->setContentSecurityPolicy($policy);
				return $response;
		}

		throw $exception;
	}

	protected function isJson(string $string): bool {
		json_decode($string);
		return json_last_error() === JSON_ERROR_NONE;
	}
}
