<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {
	/** @var IConfig */
	private $config;
	private $userId;
	public function __construct(IRequest $request, IConfig $config, $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->config = $config;
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
	public function external() {
		Util::addScript(Application::APP_ID, 'libresign-external');
		$response = new TemplateResponse(
			Application::APP_ID,
			'external',
			[
				'_libresign_config' => json_encode([
					'user' => [
						'name' => 'Jhon Doe'
					],
					'sign' => [
						'pdf' => 'http://asfadsf.asdfasdf',
						'filename' => 'Contract',
						'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.'
					]
				])
			],
			TemplateResponse::RENDER_AS_BASE
		);
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedChildSrcDomain('*');
		$policy->addAllowedFrameDomain('*');
		$response->setContentSecurityPolicy($policy);
		return $response;
	}
}
