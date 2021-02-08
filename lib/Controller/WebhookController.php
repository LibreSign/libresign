<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class WebhookController extends ApiController {
	/** @var IConfig */
	private $config;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IL10N */
	private $l;
	/** @var IUserSession */
	private $userSession;

	public function __construct(
		IRequest $request,
		IConfig $config,
		IGroupManager $groupManager,
		IUserSession $userSession,
		IL10N $l
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->userSession = $userSession;
		$this->l = $l;
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 * @return JSONResponse
	 */
	public function register() {
		$authorized = json_decode($this->config->getAppValue(Application::APP_ID, 'webhook_authorized', '["admin"]'));
		if (!empty($authorized)) {
			$userGroups = $this->groupManager->getUserGroupIds($this->userSession->getUser());
			if (!array_intersect($userGroups, $authorized)) {
				return new JSONResponse(
					[
						'message' => $this->l->t('Insufficient permissions to use API'),
					],
					Http::STATUS_FORBIDDEN
				);
			}
		}
		return new JSONResponse(
			[
				'message' => $this->l->t('Success'),
			],
			Http::STATUS_OK
		);
	}
}