<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OC\AppFramework\Middleware\Security\Exceptions\NotLoggedInException;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Middleware\Attribute\RequireSetupOk;
use OCA\Libresign\Middleware\Attribute\RequireSignRequestUuid;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\TokenService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\RequestSignatureService;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
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
		protected IUserSession $userSession,
		private SessionService $sessionService,
		private IInitialState $initialState,
		private AccountService $accountService,
		protected SignFileService $signFileService,
		protected RequestSignatureService $requestSignatureService,
		private SignerElementsService $signerElementsService,
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
	 * Index page
	 *
	 * @return TemplateResponse<Http::STATUS_OK, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSetupOk(template: 'main')]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		$this->initialState->provideInitialState('config', $this->accountService->getConfig($this->userSession->getUser()));
		$this->initialState->provideInitialState('certificate_engine', $this->accountService->getCertificateEngineName());

		try {
			$this->validateHelper->canRequestSign($this->userSession->getUser());
			$this->initialState->provideInitialState('can_request_sign', true);
		} catch (LibresignException $th) {
			$this->initialState->provideInitialState('can_request_sign', false);
		}

		$this->provideSignerSignatues();
		$this->initialState->provideInitialState('file_info', $this->fileService->formatFile());
		$this->initialState->provideInitialState('identify_methods', $this->identifyMethodService->getIdentifyMethodsSettings());
		$this->initialState->provideInitialState('legal_information', $this->appConfig->getAppValue('legal_information'));

		Util::addScript(Application::APP_ID, 'libresign-main');

		$response = new TemplateResponse(Application::APP_ID, 'main');

		$policy = new ContentSecurityPolicy();
		$policy->allowEvalScript(true);
		$policy->addAllowedFrameDomain('\'self\'');
		$response->setContentSecurityPolicy($policy);

		return $response;
	}

	/**
	 * Index page to authenticated users
	 *
	 * This router is used to be possible render pages with /f/, is a
	 * workaround at frontend side to identify pages with authenticated accounts
	 *
	 * @return TemplateResponse<Http::STATUS_OK, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSetupOk(template: 'main')]
	#[FrontpageRoute(verb: 'GET', url: '/f/')]
	public function indexF(): TemplateResponse {
		return $this->index();
	}

	/**
	 * Incomplete page
	 *
	 * @return TemplateResponse<Http::STATUS_OK, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/f/incomplete')]
	public function incomplete(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'libresign-main');
		$response = new TemplateResponse(Application::APP_ID, 'main');
		return $response;
	}

	/**
	 * Incomplete page in full screen
	 *
	 * @return TemplateResponse<Http::STATUS_OK, array{}>
	 *
	 * 200: OK
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/p/incomplete')]
	public function incompleteP(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'libresign-main');
		$response = new TemplateResponse(Application::APP_ID, 'main', [], TemplateResponse::RENDER_AS_BASE);
		return $response;
	}

	/**
	 * Main page to authenticated signer with a path
	 *
	 * The path is used only by frontend
	 *
	 * @param string $uuid Sign request uuid
	 * @return TemplateResponse<Http::STATUS_OK, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSetupOk(template: 'main')]
	#[FrontpageRoute(verb: 'GET', url: '/f/{path}', requirements: ['path' => '.+'])]
	public function indexFPath(): TemplateResponse {
		return $this->index();
	}


	/**
	 * Sign page to authenticated signer
	 *
	 * @param string $uuid Sign request uuid
	 * @return TemplateResponse<Http::STATUS_OK, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSetupOk]
	#[PublicPage]
	#[RequireSignRequestUuid]
	#[FrontpageRoute(verb: 'GET', url: '/f/sign/{uuid}')]
	public function signF(string $uuid): TemplateResponse {
		$this->initialState->provideInitialState('action', JSActions::ACTION_SIGN_INTERNAL);
		return $this->index();
	}

	/**
	 * Sign page to authenticated signer with the path of file
	 *
	 * The path is used only by frontend
	 *
	 * @param string $uuid Sign request uuid
	 * @return TemplateResponse<Http::STATUS_OK, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSetupOk]
	#[PublicPage]
	#[RequireSignRequestUuid]
	#[FrontpageRoute(verb: 'GET', url: '/f/sign/{uuid}/{path}', requirements: ['path' => '.+'])]
	public function signFPath(string $uuid): TemplateResponse {
		$this->initialState->provideInitialState('action', JSActions::ACTION_SIGN_INTERNAL);
		return $this->index();
	}

	/**
	 * Sign page to authenticated signer
	 *
	 * The path is used only by frontend
	 *
	 * @param string $uuid Sign request uuid
	 * @return TemplateResponse<Http::STATUS_OK, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSetupOk]
	#[PublicPage]
	#[RequireSignRequestUuid]
	#[FrontpageRoute(verb: 'GET', url: '/p/sign/{uuid}/pdf')]
	public function signPdf(string $uuid): TemplateResponse {
		return $this->sign($uuid);
	}

	/**
	 * Sign page to authenticated signer
	 *
	 * The path is used only by frontend
	 *
	 * @param string $uuid Sign request uuid
	 * @return TemplateResponse<Http::STATUS_OK, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSetupOk]
	#[PublicPage]
	#[RequireSignRequestUuid]
	#[FrontpageRoute(verb: 'GET', url: '/p/sign/{uuid}')]
	public function sign(string $uuid): TemplateResponse {
		$this->initialState->provideInitialState('action', JSActions::ACTION_SIGN);
		$this->initialState->provideInitialState('config',
			$this->accountService->getConfig($this->userSession->getUser())
		);
		$this->initialState->provideInitialState('filename', $this->getFileEntity()->getName());
		$file = $this->fileService
			->setFile($this->getFileEntity())
			->setMe($this->userSession->getUser())
			->setIdentifyMethodId($this->sessionService->getIdentifyMethodId())
			->setSignRequest($this->getSignRequestEntity())
			->showVisibleElements()
			->showSigners()
			->formatFile();
		$this->initialState->provideInitialState('status', $file['status']);
		$this->initialState->provideInitialState('statusText', $file['statusText']);
		$this->initialState->provideInitialState('signers', $file['signers']);
		$this->initialState->provideInitialState('sign_request_uuid', $uuid);
		$this->provideSignerSignatues();
		$this->initialState->provideInitialState('token_length', TokenService::TOKEN_LENGTH);
		$this->initialState->provideInitialState('description', $this->getSignRequestEntity()->getDescription() ?? '');
		$this->initialState->provideInitialState('pdf',
			$this->signFileService->getFileUrl('url', $this->getFileEntity(), $this->getNextcloudFile(), $uuid)
		);
		$this->initialState->provideInitialState('nodeId', $this->getFileEntity()->getNodeId());

		Util::addScript(Application::APP_ID, 'libresign-external');
		$response = new TemplateResponse(Application::APP_ID, 'external', [], TemplateResponse::RENDER_AS_BASE);

		$policy = new ContentSecurityPolicy();
		$policy->allowEvalScript(true);
		$response->setContentSecurityPolicy($policy);

		return $response;
	}

	private function provideSignerSignatues(): void {
		$signatures = [];
		if ($this->userSession->getUser()) {
			$signatures = $this->signerElementsService->getUserElements($this->userSession->getUser()->getUID());
		} else {
			$signatures = $this->signerElementsService->getElementsFromSessionAsArray();
		}
		$this->initialState->provideInitialState('user_signatures', $signatures);
	}

	/**
	 * Show signature page
	 *
	 * @param string $uuid Sign request uuid
	 * @return TemplateResponse<Http::STATUS_OK, array{}>
	 *
	 * 200: OK
	 * 404: Invalid UUID
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSetupOk]
	#[FrontpageRoute(verb: 'GET', url: '/p/account/files/approve/{uuid}')]
	#[FrontpageRoute(verb: 'GET', url: '/p/account/files/approve/{uuid}/{path}', requirements: ['path' => '.+'], postfix: 'private')]
	public function signAccountFile($uuid): TemplateResponse {
		try {
			$fileEntity = $this->signFileService->getFileByUuid($uuid);
			$this->signFileService->getAccountFileById($fileEntity->getId());
		} catch (DoesNotExistException $e) {
			throw new LibresignException(json_encode([
				'action' => JSActions::ACTION_DO_NOTHING,
				'errors' => [$this->l10n->t('Invalid UUID')],
			]), Http::STATUS_NOT_FOUND);
		}
		$this->initialState->provideInitialState('action', JSActions::ACTION_SIGN_ACCOUNT_FILE);
		$this->initialState->provideInitialState('config',
			$this->accountService->getConfig($this->userSession->getUser())
		);
		$this->initialState->provideInitialState('signer',
			$this->signFileService->getSignerData(
				$this->userSession->getUser(),
			)
		);
		$this->initialState->provideInitialState('identifyMethods',
			$this->signFileService->getAvailableIdentifyMethodsFromSettings()
		);
		$this->initialState->provideInitialState('filename', $fileEntity->getName());
		$file = $this->fileService
			->setFile($fileEntity)
			->setMe($this->userSession->getUser())
			->setIdentifyMethodId($this->sessionService->getIdentifyMethodId())
			->showVisibleElements()
			->showSigners()
			->formatFile();
		$this->initialState->provideInitialState('fileId', $file['nodeId']);
		$this->initialState->provideInitialState('status', $file['status']);
		$this->initialState->provideInitialState('statusText', $file['statusText']);
		$this->initialState->provideInitialState('visibleElements', []);
		$this->initialState->provideInitialState('signers', []);
		$this->provideSignerSignatues();
		$signatureMethods = $this->identifyMethodService->getSignMethodsOfIdentifiedFactors($this->getSignRequestEntity()->getId());
		$this->initialState->provideInitialState('signature_methods', $signatureMethods);
		$this->initialState->provideInitialState('token_length', TokenService::TOKEN_LENGTH);
		$this->initialState->provideInitialState('description', '');
		$nextcloudFile = $this->signFileService->getNextcloudFile($fileEntity->getNodeId());
		$this->initialState->provideInitialState('pdf',
			$this->signFileService->getFileUrl('url', $fileEntity, $nextcloudFile, $uuid)
		);

		Util::addScript(Application::APP_ID, 'libresign-external');
		$response = new TemplateResponse(Application::APP_ID, 'external', [], TemplateResponse::RENDER_AS_BASE);

		$policy = new ContentSecurityPolicy();
		$policy->allowEvalScript(true);
		$response->setContentSecurityPolicy($policy);

		return $response;
	}

	/**
	 * Use UUID of file to get PDF
	 *
	 * @param string $uuid File uuid
	 * @return FileDisplayResponse<Http::STATUS_OK, array{Content-Type: string}>|DataResponse<Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 200: OK
	 * 401: Validation page not accessible if unauthenticated
	 * 404: File not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSetupOk]
	#[PublicPage]
	#[AnonRateLimit(limit: 30, period: 60)]
	#[FrontpageRoute(verb: 'GET', url: '/p/pdf/{uuid}')]
	public function getPdf($uuid) {
		$this->throwIfValidationPageNotAccessible();
		try {
			$file = $this->accountService->getPdfByUuid($uuid);
		} catch (DoesNotExistException $th) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		return new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => $file->getMimeType()]);
	}

	/**
	 * Use UUID of user to get PDF
	 *
	 * @param string $uuid Sign request uuid
	 * @return FileDisplayResponse<Http::STATUS_OK, array{Content-Type: string}>
	 *
	 * 200: OK
	 * 401: Validation page not accessible if unauthenticated
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSignRequestUuid]
	#[PublicPage]
	#[RequireSetupOk]
	#[AnonRateLimit(limit: 30, period: 60)]
	#[FrontpageRoute(verb: 'GET', url: '/pdf/{uuid}')]
	public function getPdfFile($uuid): FileDisplayResponse {
		$this->throwIfValidationPageNotAccessible();
		$file = $this->getNextcloudFile();
		return new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => $file->getMimeType()]);
	}

	/**
	 * Show validation page
	 *
	 * @return TemplateResponse<Http::STATUS_OK, array{}>
	 *
	 * 200: OK
	 * 401: Validation page not accessible if unauthenticated
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSetupOk(template: 'validation')]
	#[PublicPage]
	#[AnonRateLimit(limit: 30, period: 60)]
	#[FrontpageRoute(verb: 'GET', url: '/p/validation')]
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
	 *
	 * The path is used only by frontend
	 *
	 * @param string $uuid Sign request uuid
	 * @return RedirectResponse<Http::STATUS_SEE_OTHER, array{}>
	 *
	 * 303: Redirected to validation page
	 * 401: Validation page not accessible if unauthenticated
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSetupOk]
	#[PublicPage]
	#[AnonRateLimit(limit: 30, period: 60)]
	#[FrontpageRoute(verb: 'GET', url: '/validation/{uuid}')]
	public function validationFileWithShortUrl(): RedirectResponse {
		$this->throwIfValidationPageNotAccessible();
		return new RedirectResponse($this->url->linkToRoute('libresign.page.validationFile', ['uuid' => $this->request->getParam('uuid')]));
	}

	/**
	 * Show validation page
	 *
	 * @param string $uuid Sign request uuid
	 * @return TemplateResponse<Http::STATUS_OK, array{}>
	 *
	 * 200: OK
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSetupOk(template: 'main')]
	#[PublicPage]
	#[RequireSignRequestUuid]
	#[FrontpageRoute(verb: 'GET', url: '/reset-password')]
	public function resetPassword(): TemplateResponse {
		$this->initialState->provideInitialState('config',
			$this->accountService->getConfig($this->userSession->getUser())
		);

		Util::addScript(Application::APP_ID, 'libresign-main');
		$response = new TemplateResponse(Application::APP_ID, 'reset_password');

		return $response;
	}

	/**
	 * Show validation page for a specific file UUID
	 *
	 * @param string $uuid File uuid
	 * @return TemplateResponse<Http::STATUS_OK, array{}>
	 *
	 * 200: OK
	 * 401: Validation page not accessible if unauthenticated
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[RequireSetupOk(template: 'validation')]
	#[PublicPage]
	#[AnonRateLimit(limit: 30, period: 60)]
	#[FrontpageRoute(verb: 'GET', url: '/p/validation/{uuid}')]
	public function validationFile(string $uuid): TemplateResponse {
		$this->throwIfValidationPageNotAccessible();
		try {
			$this->signFileService->getFileByUuid($uuid);
			$this->fileService->setFileByType('uuid', $uuid);
		} catch (DoesNotExistException $e) {
			try {
				$signRequest = $this->signFileService->getSignRequest($uuid);
				$libresignFile = $this->signFileService->getFile($signRequest->getFileId());
				$this->fileService->setFile($libresignFile);
			} catch (DoesNotExistException $e) {
				$this->initialState->provideInitialState('action', JSActions::ACTION_DO_NOTHING);
				$this->initialState->provideInitialState('errors', [$this->l10n->t('Invalid UUID')]);
			}
		}
		if ($this->userSession->isLoggedIn()) {
			$this->initialState->provideInitialState('config',
				$this->accountService->getConfig($this->userSession->getUser())
			);
		} else {
			$this->initialState->provideInitialState('config',
				$this->accountService->getConfig()
			);
		}

		$this->initialState->provideInitialState('legal_information', $this->appConfig->getAppValue('legal_information'));

		$this->fileService->showSigners();
		$this->initialState->provideInitialState('file_info', $this->fileService->formatFile());

		Util::addScript(Application::APP_ID, 'libresign-validation');
		$response = new TemplateResponse(Application::APP_ID, 'validation', [], TemplateResponse::RENDER_AS_BASE);

		return $response;
	}

	private function throwIfValidationPageNotAccessible(): void {
		$isValidationUrlPrivate = (bool)$this->appConfig->getAppValue('make_validation_url_private', '0');
		if ($this->userSession->isLoggedIn()) {
			return;
		}
		if ($isValidationUrlPrivate) {
			throw new NotLoggedInException();
		}
	}
}
