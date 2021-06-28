<?php

namespace OCA\Libresign\Controller;

use OC\Security\CSP\ContentSecurityPolicy;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\AccountService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserManager;
use OCP\Util;

class PageController extends Controller {
	/** @var ISession */
	private $session;
	/** @var IUserManager */
	protected $userManager;
	/** @var IInitialState */
	private $initialState;
	/** @var AccountService */
	private $accountService;
	public function __construct(
		IRequest $request,
		ISession $session,
		IUserManager $userManager,
		IInitialState $initialState,
		AccountService $accountService
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->session = $session;
		$this->initialState = $initialState;
		$this->userManager = $userManager;
		$this->accountService = $accountService;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * Render default template
	 */
	public function index() {
		$this->initialState->provideInitialState('config', json_encode($this->accountService->getConfig(
			$this->request->getParam('uuid'),
			$this->session->get('user_id'),
			'url'
		)));

		Util::addScript(Application::APP_ID, 'libresign-main');

		$response = new TemplateResponse(Application::APP_ID, 'main');

		return $response;
	}

	/**
	 * Show signature page
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function sign($uuid) {
		$this->initialState->provideInitialState('config', json_encode($this->accountService->getConfig(
			$uuid,
			$this->session->get('user_id'),
			'url'
		)));

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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function getPdf($uuid) {
		try {
			$file = $this->accountService->getPdfByUuid($uuid);
		} catch (\Throwable $th) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$resp = new FileDisplayResponse($file);
		$resp->addHeader('Content-Type', 'application/pdf');

		$csp = new ContentSecurityPolicy();
		$csp->setInlineScriptAllowed(true);
		$resp->setContentSecurityPolicy($csp);

		return $resp;
	}

	/**
	 * Use UUID of user to get PDF
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getPdfUser($uuid) {
		$config = $this->accountService->getConfig(
			$uuid,
			$this->session->get('user_id'),
			'file'
		);
		if (!isset($config['sign'])) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		$resp = new FileDisplayResponse($config['sign']['pdf']['file']);
		$resp->addHeader('Content-Type', 'application/pdf');

		$csp = new ContentSecurityPolicy();
		$csp->setInlineScriptAllowed(true);
		$resp->setContentSecurityPolicy($csp);

		return $resp;
	}

	/**
	 * Show validation page
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function validation() {
		$this->initialState->provideInitialState('config', json_encode($this->accountService->getConfig(
			$this->request->getParam('uuid'),
			$this->session->get('user_id'),
			'url'
		)));

		Util::addScript(Application::APP_ID, 'libresign-validation');
		$response = new TemplateResponse(Application::APP_ID, 'validation', [], TemplateResponse::RENDER_AS_BASE);

		return $response;
	}

	/**
	 * Show validation page
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function resetPassword() {
		$this->initialState->provideInitialState('config', json_encode($this->accountService->getConfig(
			$this->request->getParam('uuid'),
			$this->session->get('user_id'),
			'url'
		)));

		Util::addScript(Application::APP_ID, 'libresign-main');
		$response = new TemplateResponse(Application::APP_ID, 'reset_password');

		return $response;
	}

	/**
	 * Show validation page for a specific file UUID
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function validationFile(string $uuid) {
		$this->initialState->provideInitialState('config', json_encode($this->accountService->getConfig(
			$uuid,
			$this->session->get('user_id'),
			'url'
		)));

		Util::addScript(Application::APP_ID, 'libresign-validation');
		$response = new TemplateResponse(Application::APP_ID, 'validation', [], TemplateResponse::RENDER_AS_BASE);

		return $response;
	}
}
