<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\WebhookService;
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
	private $l10n;
	/** @var IUserSession */
	private $userSession;
	/** @var WebhookService */
	private $service;

	public function __construct(
		IRequest $request,
		IConfig $config,
		IGroupManager $groupManager,
		IUserSession $userSession,
		IL10N $l10n,
		WebhookService $service
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->userSession = $userSession;
		$this->l10n = $l10n;
		$this->service = $service;
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 * @return JSONResponse
	 */
	public function register(array $file, array $users, ?string $callback = null) {
		$authorized = json_decode($this->config->getAppValue(Application::APP_ID, 'webhook_authorized', '["admin"]'));
		if (!empty($authorized)) {
			$userGroups = $this->groupManager->getUserGroupIds($this->userSession->getUser());
			if (!array_intersect($userGroups, $authorized)) {
				return new JSONResponse(
					[
						'message' => $this->l10n->t('Insufficient permissions to use API'),
					],
					Http::STATUS_FORBIDDEN
				);
			}
		}
		$response = $this->service->validate([
			'file'     => $file,
			'users'    => $users,
			'callback' => $callback
		]);
		if (!empty($response)) {
			return $response;
		}
		return new JSONResponse(
			[
				'message' => $this->l10n->t('Success'),
			],
			Http::STATUS_OK
		);
	}
}