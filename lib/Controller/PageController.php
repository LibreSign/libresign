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

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;

class PageController extends AEnvironmentPageAwareController {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private IInitialState $initialState,
		private AccountService $accountService,
		private IdentifyMethodService $identifyMethodService,
		private IAppConfig $appConfig,
		private FileService $fileService,
		private ValidateHelper $validateHelper,
		private IURLGenerator $url
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Render default template
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		$this->initialState->provideInitialState('config', $this->accountService->getConfig(
			'file_user_uuid',
			$this->request->getParam('uuid'),
			$this->userSession->getUser(),
			'url'
		));

		try {
			$this->validateHelper->canRequestSign($this->userSession->getUser());
			$this->initialState->provideInitialState('can_request_sign', true);
		} catch (LibresignException $th) {
			$this->initialState->provideInitialState('can_request_sign', false);
		}

		$this->initialState->provideInitialState('file_info', $this->fileService->formatFile());
		$this->initialState->provideInitialState('identify_methods', $this->identifyMethodService->getIdentifyMethodsSettings());
		$this->initialState->provideInitialState('legal_information', $this->appConfig->getAppValue('legal_information'));

		Util::addScript(Application::APP_ID, 'libresign-main');

		$response = new TemplateResponse(Application::APP_ID, 'main');

		return $response;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function indexF(): TemplateResponse {
		return $this->index();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function indexFPath(): TemplateResponse {
		return $this->index();
	}

	/**
	 * Show signature page
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function sign($uuid): TemplateResponse {
		$this->initialState->provideInitialState('config', $this->accountService->getConfig(
			'file_user_uuid',
			$uuid,
			$this->userSession->getUser(),
			'url'
		));

		Util::addScript(Application::APP_ID, 'libresign-external');
		$response = new TemplateResponse(Application::APP_ID, 'external', [], TemplateResponse::RENDER_AS_BASE);

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedFrameDomain('\'self\'');
		$response->setContentSecurityPolicy($policy);

		return $response;
	}

	/**
	 * Show signature page
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function signAccountFile($uuid): TemplateResponse {
		$this->initialState->provideInitialState('config', $this->accountService->getConfig(
			'file_uuid',
			$uuid,
			$this->userSession->getUser(),
			'url'
		));

		Util::addScript(Application::APP_ID, 'libresign-external');
		$response = new TemplateResponse(Application::APP_ID, 'external', [], TemplateResponse::RENDER_AS_BASE);

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedFrameDomain('\'self\'');
		$response->setContentSecurityPolicy($policy);

		return $response;
	}

	/**
	 * Use UUID of file to get PDF
	 *
	 * @return DataResponse|FileDisplayResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function getPdf($uuid) {
		try {
			$file = $this->accountService->getPdfByUuid($uuid);
		} catch (DoesNotExistException $th) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$resp = new FileDisplayResponse($file);
		$resp->addHeader('Content-Type', 'application/pdf');

		return $resp;
	}

	/**
	 * Use UUID of user to get PDF
	 *
	 * @return DataResponse|FileDisplayResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getPdfUser($uuid) {
		$config = $this->accountService->getConfig(
			'file_user_uuid',
			$uuid,
			$this->userSession->getUser(),
			'file'
		);
		if (!isset($config['sign'])) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		$resp = new FileDisplayResponse($config['sign']['pdf']['file']);
		$resp->addHeader('Content-Type', 'application/pdf');
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedFrameDomain('\'self\'');
		$resp->setContentSecurityPolicy($csp);

		return $resp;
	}

	/**
	 * Show validation page
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function validation(): TemplateResponse {
		$this->initialState->provideInitialState('config', $this->accountService->getConfig(
			'file_user_uuid',
			$this->request->getParam('uuid'),
			$this->userSession->getUser(),
			'url'
		));

		Util::addScript(Application::APP_ID, 'libresign-validation');
		$response = new TemplateResponse(Application::APP_ID, 'validation', [], TemplateResponse::RENDER_AS_BASE);

		return $response;
	}

	/**
	 * Show validation page
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function validationFileWithShortUrl(): RedirectResponse {
		return new RedirectResponse($this->url->linkToRoute('libresign.page.validationFile', ['uuid' => $this->request->getParam('uuid')]));
	}

	/**
	 * Show validation page
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function resetPassword(): TemplateResponse {
		$this->initialState->provideInitialState('config', $this->accountService->getConfig(
			'file_user_uuid',
			$this->request->getParam('uuid'),
			$this->userSession->getUser(),
			'url'
		));

		Util::addScript(Application::APP_ID, 'libresign-main');
		$response = new TemplateResponse(Application::APP_ID, 'reset_password');

		return $response;
	}

	/**
	 * Show validation page for a specific file UUID
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function validationFile(string $uuid): TemplateResponse {
		$this->initialState->provideInitialState('config', $this->accountService->getConfig(
			'file_uuid',
			$uuid,
			$this->userSession->getUser(),
			'url'
		));

		$this->initialState->provideInitialState('legal_information', $this->appConfig->getAppValue('legal_information'));

		$this->fileService
			->setFileByType('uuid', $uuid)
			->showSigners();
		$this->initialState->provideInitialState('file_info', $this->fileService->formatFile());

		Util::addScript(Application::APP_ID, 'libresign-validation');
		$response = new TemplateResponse(Application::APP_ID, 'validation', [], TemplateResponse::RENDER_AS_BASE);

		return $response;
	}
}
