<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\AccountService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;

class AccountController extends ApiController {
	/** @var IL10N */
	private $l10n;
	/** @var AccountService */
	private $account;

	public function __construct(
		IRequest $request,
		IL10N $l10n,
		AccountService $account
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->l10n = $l10n;
		$this->account = $account;
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 * @return JSONResponse
	 */
	public function createToSign(string $uuid, string $email) {
		try {
			$data = [
				'uuid' => $uuid,
				'email' => $email
			];
			$this->account->validateCreateToSign($data);
		} catch (\Throwable $th) {
			return new JSONResponse(
				[
					'message' => $th->getMessage(),
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		return new JSONResponse(
			[
				'message' => $this->l10n->t('Success'),
				'data' => $return
			],
			Http::STATUS_OK
		);
	}
}
