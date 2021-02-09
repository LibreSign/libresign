<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\WebhookService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class WebhookController extends ApiController {
	/** @var IUserSession */
	private $userSession;
	/** @var IL10N */
	private $l10n;
	/** @var WebhookService */
	private $service;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IL10N $l10n,
		WebhookService $service
	) {
		parent::__construct(Application::APP_ID, $request);
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
	public function register(array $file, array $users, string $name, ?string $callback = null) {
		$user = $this->userSession->getUser();
		$data = [
			'file' => $file,
			'name' => $name,
			'users' => $users,
			'callback' => $callback,
			'userManager' => $user
		];
		try {
			$this->service->validate($data);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'message' => $th->getMessage(),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		$this->service->save($data);
		return new JSONResponse(
			[
				'message' => $this->l10n->t('Success'),
			],
			Http::STATUS_OK
		);
	}
}
