<?php

namespace OCA\Libresign\Controller;

use OC\Files\Filesystem;
use OC\Security\CSP\ContentSecurityPolicy;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Helper\JSConfigHelper;
use OCA\Libresign\Db\FileMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {
	/** @var IConfig */
	private $config;
	/** @var JSConfigHelper */
	private $jsConfigHelper;
	/** @var FileMapper */
	private $fileMapper;
	/** @var IRootFolder */
	private $root;
	public function __construct(
		IRequest $request,
		IConfig $config,
		FileMapper $fileMapper,
		IRootFolder $root,
		JSConfigHelper $jsConfigHelper
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->config = $config;
		$this->jsConfigHelper = $jsConfigHelper;
		$this->root = $root;
		$this->fileMapper = $fileMapper;
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
		$config = $this->jsConfigHelper->getConfig('file');
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
		Util::addScript(Application::APP_ID, 'libresign-validation');
		$response = new TemplateResponse(Application::APP_ID, 'validation', [], TemplateResponse::RENDER_AS_BASE);

		return $response;
	}
}
