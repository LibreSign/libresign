<?php

namespace OCA\Libresign\Controller;

use OC\Security\CSP\ContentSecurityPolicy;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Helper\JSConfigHelper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {
	/** @var IConfig */
	private $config;
	/** @var JSConfigHelper */
	private $jsConfigHelper;
	private $userId;
	public function __construct(
		IRequest $request,
		IConfig $config,
		JSConfigHelper $jsConfigHelper,
		$userId
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->config = $config;
		$this->jsConfigHelper = $jsConfigHelper;
		$this->userId = $userId;
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function sign($uuid) {
		Util::addScript(Application::APP_ID, 'libresign-external');
		$response = new TemplateResponse(Application::APP_ID, 'external', [], TemplateResponse::RENDER_AS_BASE);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function getPdf($uuid) {
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
}
