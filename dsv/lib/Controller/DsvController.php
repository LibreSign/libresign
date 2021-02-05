<?php

namespace OCA\Dsv\Controller;

use OCA\Dsv\AppInfo\Application;
use OCA\Dsv\Service\DsvService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class DsvController extends Controller {
	protected $metadataService;

	public function __construct($appName, IRequest $request, DsvService $metadataService) {
		parent::__construct($appName, $request);
		$this->metadataService = $metadataService;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param mixed $source
	 */
	public function get($source) {
		try {
			$signatures = $this->metadataService->getSignatures($source);

			return new JSONResponse([
				'response' => 'success',
				'signatures' => $signatures,
			]);
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, ['app' => Application::APP_NAME]);

			return new JSONResponse(
				[
					'response' => 'error',
					'msg' => 'Erro ao consultar assinatura digital, verifique os logs do sistema para mais detalhes.',
				]
			);
		}
	}
}
