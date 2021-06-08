<?php

namespace OCA\Libresign\Controller;

use OC\Files\Filesystem;
use OC\Security\CSP\ContentSecurityPolicy;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Service\AccountService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserManager;
use OCP\Util;

class PageController extends Controller {
	/** @var IConfig */
	private $config;
	/** @var ISession */
	private $session;
	/** @var FileMapper */
	private $fileMapper;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var IUserManager */
	protected $userManager;
	/** @var IL10N */
	private $l10n;
	/** @var IInitialState */
	private $initialState;
	/** @var AccountService */
	private $accountService;
	/** @var IRootFolder */
	private $root;
	public function __construct(
		IRequest $request,
		IConfig $config,
		ISession $session,
		FileMapper $fileMapper,
		FileUserMapper $fileUserMapper,
		IUserManager $userManager,
		IL10N $l10n,
		IInitialState $initialState,
		AccountService $accountService,
		IRootFolder $root
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->config = $config;
		$this->session = $session;
		$this->initialState = $initialState;
		$this->root = $root;
		$this->fileMapper = $fileMapper;
		$this->fileUserMapper = $fileUserMapper;
		$this->l10n = $l10n;
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

		if ($this->config->getSystemValue('debug')) {
			$csp = new ContentSecurityPolicy();
			$csp->setInlineScriptAllowed(true);
			$response->setContentSecurityPolicy($csp);
		}

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
			$fileData = $this->fileMapper->getByUuid($uuid);
			Filesystem::initMountPoints($fileData->getUserId());

			$file = $this->root->getById($fileData->getNodeId())[0];
			$filePath = $file->getPath();

			$fileUser = $this->fileUserMapper->getByFileId($fileData->getId());
			$signedUsers = array_filter($fileUser, function ($row) {
				return !is_null($row->getSigned());
			});
			if (count($fileUser) === count($signedUsers)) {
				$filePath = preg_replace(
					'/' . $file->getExtension() . '$/',
					$this->l10n->t('signed') . '.' . $file->getExtension(),
					$filePath
				);
			}
			if ($this->root->nodeExists($filePath)) {
				/** @var \OCP\Files\File */
				$file = $this->root->get($filePath);
			}
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
