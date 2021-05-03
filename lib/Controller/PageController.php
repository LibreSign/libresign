<?php

namespace OCA\Libresign\Controller;

use OC\Files\Filesystem;
use OC\Security\CSP\ContentSecurityPolicy;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Helper\JSActions;
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
use OCP\IURLGenerator;
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
	/** @var IURLGenerator */
	private $urlGenerator;
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
		IURLGenerator $urlGenerator,
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
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * Render default template
	 */
	public function index() {
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
		$this->initialState->provideInitialState('config', json_encode($this->getConfig('url')));

		Util::addScript(Application::APP_ID, 'libresign-external');
		$response = new TemplateResponse(Application::APP_ID, 'external', [], TemplateResponse::RENDER_AS_BASE);

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedFrameDomain('\'self\'');
		$response->setContentSecurityPolicy($policy);

		return $response;
	}

	/**
	 * Undocumented function
	 *
	 * @param string $formatOfPdfOnSign (base64,url,file)
	 * @return array|string
	 */
	public function getConfig(string $formatOfPdfOnSign): array {
		$uuid = $this->request->getParam('uuid');
		$userId = $this->session->get('user_id');
		try {
			$fileUser = $this->fileUserMapper->getByUuid($uuid);
		} catch (\Throwable $th) {
			$return['action'] = JSActions::ACTION_DO_NOTHING;
			$return['errors'][] = $this->l10n->t('Invalid UUID');
			return $return;
		}
		$fileUserId = $fileUser->getUserId();
		if (!$fileUserId) {
			if ($userId) {
				$return['action'] = JSActions::ACTION_DO_NOTHING;
				$return['errors'][] = $this->l10n->t('This is not your file');
				return $return;
			}
			if ($this->userManager->userExists($fileUser->getEmail())) {
				$return['action'] = JSActions::ACTION_REDIRECT;
				$return['errors'][] = $this->l10n->t('User already exists. Please login.');
				$return['redirect'] = $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
					'redirect_url' => $this->urlGenerator->linkToRoute(
						'libresign.page.sign',
						['uuid' => $uuid]
					),
				]);
				return $return;
			}
			$return['action'] = JSActions::ACTION_CREATE_USER;
			return $return;
		}
		if ($fileUser->getSigned()) {
			$return['action'] = JSActions::ACTION_SHOW_ERROR;
			$return['errors'][] = $this->l10n->t('File already signed.');
			return $return;
		}
		if (!$userId) {
			$return['action'] = JSActions::ACTION_REDIRECT;

			$return['redirect'] = $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
				'redirect_url' => $this->urlGenerator->linkToRoute(
					'libresign.page.sign',
					['uuid' => $uuid]
				),
			]);
			$return['errors'][] = $this->l10n->t('You are not logged in. Please log in.');
			return $return;
		}
		if ($fileUserId !== $userId) {
			$return['action'] = JSActions::ACTION_DO_NOTHING;
			$return['errors'][] = $this->l10n->t('Invalid user');
			return $return;
		}
		$fileData = $this->fileMapper->getById($fileUser->getFileId());
		Filesystem::initMountPoints($fileData->getUserId());
		$fileToSign = $this->root->getById($fileData->getNodeId());
		if (count($fileToSign) < 1) {
			$return['action'] = JSActions::ACTION_DO_NOTHING;
			$return['errors'][] = $this->l10n->t('File not found');
			return $return;
		}
		/** @var File */
		$fileToSign = $fileToSign[0];
		$return['action'] = JSActions::ACTION_SIGN;
		$return['user']['name'] = $fileUser->getDisplayName();
		switch ($formatOfPdfOnSign) {
			case 'base64':
				$pdf = ['base64' => base64_encode($fileToSign->getContent())];
				break;
			case 'url':
				$pdf = ['url' => $this->urlGenerator->linkToRoute('libresign.page.getPdfUser', ['uuid' => $uuid])];
				break;
			case 'file':
				$pdf = ['file' => $fileToSign];
				break;
		}
		$return['sign'] = [
			'pdf' => $pdf,
			'filename' => $fileData->getName(),
			'description' => $fileUser->getDescription()
		];
		return $return;
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
			$file = $this->fileMapper->getByUuid($uuid);
			Filesystem::initMountPoints($file->getUserId());
			$fileToSign = $this->root->getById($file->getNodeId());
		} catch (\Throwable $th) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$resp = new FileDisplayResponse($fileToSign[0]);
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
		$config = $this->getConfig('file');
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
		$this->initialState->provideInitialState('config', json_encode($this->getConfig('url')));

		Util::addScript(Application::APP_ID, 'libresign-validation');
		$response = new TemplateResponse(Application::APP_ID, 'validation', [], TemplateResponse::RENDER_AS_BASE);

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
		Util::addScript(Application::APP_ID, 'libresign-validation-teste.js');
		Util::addScript(Application::APP_ID, 'libresign-validation');
		$response = new TemplateResponse(Application::APP_ID, 'validation', [], TemplateResponse::RENDER_AS_BASE);

		return $response;
	}
}
