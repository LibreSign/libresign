<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Dashboard\DashboardService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class DashboardController extends AEnvironmentAwareController {

	private IUserSession $userSession;
	private LoggerInterface $logger;

	private DashboardService $dashboardService;

	public function __construct(
		IRequest $request,
		DashboardService $dashboardService,
		IUserSession $userSession,
		LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->userSession = $userSession;
		$this->logger = $logger;
		$this->dashboardService = $dashboardService;
	}


	/**
	 * (Optional) Get dashboard data
	 *
	 * Useful for:
	 * - user data
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(
		verb: 'GET',
		url: '/api/{apiVersion}/dashboard/details',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function getDashboardDetails(
		?int $documentsPage = 1,
		?int $documentsLength = 5
		): DataResponse {
			try {
				$user = $this->userSession->getUser();

				if (!$user) {
					return new DataResponse([
						'error' => 'Unauthorized'
					], Http::STATUS_UNAUTHORIZED);
				}

				$dashboardDetails = $this->dashboardService->getDashboardDetails(
					$user,
					$documentsPage,
					$documentsLength
				);

				return new DataResponse([
					'dashboardDetails' => $dashboardDetails->toArray(),
				], Http::STATUS_OK);

			} catch (\Throwable $e) {
				$this->logger->error('Failed to get dashboard details: ' . $e->getMessage(), [
					'exception' => $e
				]);

				return new DataResponse([
					'success' => false,
					'error' => $e->getMessage(),
					'trace' => $e->getTrace(),
				], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
	}
}
