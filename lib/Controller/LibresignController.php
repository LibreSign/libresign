<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\LibresignService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class LibresignController extends Controller {
	use HandleErrorsTrait;
	use HandleParamsTrait;

	/** @var LibresignService */
	private $service;

	/** @var string */
	private $userId;

	public function __construct(
		IRequest $request,
		LibresignService $service,
		$userId
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->service = $service;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @todo remove NoCSRFRequired
	 */
	public function sign(
		string $inputFilePath = null,
		string $outputFolderPath = null,
		string $certificatePath = null,
		string $password = null
	): DataResponse {
		try {
			$this->checkParams([
				'inputFilePath' => $inputFilePath,
				'outputFolderPath' => $outputFolderPath,
				'certificatePath' => $certificatePath,
				'password' => $password,
			]);

			$fileSigned = $this->service->sign($inputFilePath, $outputFolderPath, $certificatePath, $password);

			return new DataResponse(['fileSigned' => $fileSigned->getInternalPath()]);
		} catch (\Exception $exception) {
			return $this->handleErrors($exception);
		}
	}
}
