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

use OC\AppFramework\Middleware\Security\Exceptions\NotLoggedInException;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\Attribute\CanSignRequestUuid;
use OCA\Libresign\Middleware\Attribute\RequireSignRequestUuid;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\RequestSignatureService;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
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
use OCP\IL10N;
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
		protected SignFileService $signFileService,
		protected RequestSignatureService $requestSignatureService,
		protected IL10N $l10n,
		private IdentifyMethodService $identifyMethodService,
		private IAppConfig $appConfig,
		private FileService $fileService,
		private ValidateHelper $validateHelper,
		private IURLGenerator $url
	) {
		parent::__construct(
			request: $request,
			signFileService: $signFileService,
			l10n: $l10n,
			userSession: $userSession,
		);
	}

	/**
	 * Render default template
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		$this->initialState->provideInitialState('config', $this->accountService->getConfig($this->userSession->getUser()));
		$this->initialState->provideInitialState('certificate_engine', $this->accountService->getCertificateEngineName());

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
	#[RequireSignRequestUuid]
	public function sign($uuid): TemplateResponse {
		$this->initialState->provideInitialState('action', JSActions::ACTION_SIGN);
		$this->initialState->provideInitialState('config',
			$this->accountService->getConfig($this->userSession->getUser())
		);
		$this->initialState->provideInitialState('signer',
			$this->signFileService->getSignerData(
				$this->userSession->getUser(),
				$this->getSignRequestEntity(),
			)
		);
		$this->initialState->provideInitialState('identifyMethods',
			$this->signFileService->getAvailableIdentifyMethods($this->getSignRequestEntity())
		);
		$this->initialState->provideInitialState('filename', $this->getFileEntity()->getName());
		$file = $this->fileService
			->setFile($this->getFileEntity())
			->showVisibleElements()
			->showSigners()
			->formatFile();
		$this->initialState->provideInitialState('status', $file['status']);
		$this->initialState->provideInitialState('visibleElements', $file['visibleElements']);
		$this->initialState->provideInitialState('signers', $file['signers']);
		$this->initialState->provideInitialState('description', $this->getSignRequestEntity()->getDescription() ?? '');
		$this->initialState->provideInitialState('pdf',
			$this->signFileService->getFileUrl('url', $this->getFileEntity(), $this->getNextcloudFile(), $uuid)
		);

		Util::addScript(Application::APP_ID, 'libresign-external');
		$response = new TemplateResponse(Application::APP_ID, 'external', [], TemplateResponse::RENDER_AS_BASE);

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedFrameDomain('\'self\'');
		$response->setContentSecurityPolicy($policy);

		return $response;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[CanSignRequestUuid]
	public function signRenew(string $method): TemplateResponse {
		$this->requestSignatureService->renew(
			$this->getSignRequestEntity(),
			$method,
		);
		$this->initialState->provideInitialState('action', JSActions::ACTION_DO_NOTHING);
		// TRANSLATORS Message sent to signer when the sign link was expired and was possible to request to renew. The signer will see this message on the screen and nothing more.
		$this->initialState->provideInitialState('message', $this->l10n->t('Renewed with success. Access the link again.'));
		Util::addScript(Application::APP_ID, 'libresign-external');
		return new TemplateResponse(Application::APP_ID, 'external', [], TemplateResponse::RENDER_AS_BASE);
	}

	/**
	 * Show signature page
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function signAccountFile($uuid): TemplateResponse {
		try {
			$fileEntity = $this->signFileService->getFileByUuid($uuid);
			$this->signFileService->getAccountFileById($fileEntity->getId());
		} catch (DoesNotExistException $e) {
			$this->initialState->provideInitialState('action', JSActions::ACTION_DO_NOTHING);
			$this->initialState->provideInitialState('errors', [$this->l10n->t('Invalid UUID')]);
		}
		$this->initialState->provideInitialState('config',
			$this->accountService->getConfig($this->userSession->getUser())
		);

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
	#[AnonRateLimit(limit: 5, period: 120)]
	public function getPdf($uuid) {
		$this->throwIfValidationPageNotAccessible();
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
	#[RequireSignRequestUuid]
	#[AnonRateLimit(limit: 5, period: 120)]
	public function getPdfUser($uuid) {
		$resp = new FileDisplayResponse($this->getNextcloudFile());
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
	#[AnonRateLimit(limit: 5, period: 120)]
	public function validation(): TemplateResponse {
		$this->throwIfValidationPageNotAccessible();
		if ($this->getFileEntity()) {
			$this->initialState->provideInitialState('config',
				$this->accountService->getConfig($this->userSession->getUser())
			);
			$this->initialState->provideInitialState('file', [
				'uuid' => $this->getFileEntity()?->getUuid(),
				'description' => $this->getSignRequestEntity()?->getDescription(),
			]);
			$this->initialState->provideInitialState('filename', $this->getFileEntity()?->getName());
			$this->initialState->provideInitialState('pdf',
				$this->signFileService->getFileUrl('url', $this->getFileEntity(), $this->getNextcloudFile(), $this->request->getParam('uuid'))
			);
			$this->initialState->provideInitialState('signer',
				$this->signFileService->getSignerData(
					$this->userSession->getUser(),
					$this->getSignRequestEntity(),
				)
			);
		}

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
	#[AnonRateLimit(limit: 5, period: 120)]
	public function validationFileWithShortUrl(): RedirectResponse {
		$this->throwIfValidationPageNotAccessible();
		return new RedirectResponse($this->url->linkToRoute('libresign.page.validationFile', ['uuid' => $this->request->getParam('uuid')]));
	}

	/**
	 * Show validation page
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequireSignRequestUuid]
	public function resetPassword(): TemplateResponse {
		$this->initialState->provideInitialState('config',
			$this->accountService->getConfig($this->userSession->getUser()),
		);

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
	#[AnonRateLimit(limit: 5, period: 120)]
	public function validationFile(string $uuid): TemplateResponse {
		$this->throwIfValidationPageNotAccessible();
		try {
			$fileEntity = $this->signFileService->getFileByUuid($uuid);
			$this->signFileService->getAccountFileById($fileEntity->getId());
		} catch (DoesNotExistException $e) {
			$this->initialState->provideInitialState('action', JSActions::ACTION_DO_NOTHING);
			$this->initialState->provideInitialState('errors', [$this->l10n->t('Invalid UUID')]);
		}
		$this->initialState->provideInitialState('config',
			$this->accountService->getConfig($this->userSession->getUser())
		);

		$this->initialState->provideInitialState('legal_information', $this->appConfig->getAppValue('legal_information'));

		$this->fileService
			->setFileByType('uuid', $uuid)
			->showSigners();
		$this->initialState->provideInitialState('file_info', $this->fileService->formatFile());

		Util::addScript(Application::APP_ID, 'libresign-validation');
		$response = new TemplateResponse(Application::APP_ID, 'validation', [], TemplateResponse::RENDER_AS_BASE);

		return $response;
	}

	private function throwIfValidationPageNotAccessible(): void {
		$isValidationUrlPrivate = (bool) $this->appConfig->getAppValue('make_validation_url_private', '0');
		if ($this->userSession->isLoggedIn()) {
			return;
		}
		if ($isValidationUrlPrivate) {
			throw new NotLoggedInException();
		}
	}
}
