<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\AdminSignatureService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class AdminController extends Controller
{
    use HandleErrorsTrait;
    use HandleParamsTrait;

    /** @var AdminSignatureService */
    private $service;

    /** @var string */
    private $userId;

    public function __construct(
        IRequest $request,
        AdminSignatureService $service,
        $userId
    ) {
        parent::__construct(Application::APP_ID, $request);
        $this->service = $service;
        $this->userId = $userId;
    }

    /**
     * @NoCSRFRequired
     *
     * @todo remove NoCSRFRequired
     */
    public function generateCertificate(
        string $commonName = null,
        string $country = null,
        string $organization = null,
        string $organizationUnit = null,
        string $cfsslUri = null
    ): DataResponse {
        try {
            $this->checkParams([
                'commonName' => $commonName,
                'country' => $country,
                'organization' => $organization,
                'organizationUnit' => $organizationUnit,
                'cfsslUri' => $cfsslUri,
            ]);

            $this->service->generate(
                $commonName,
                $country,
                $organization,
                $organizationUnit,
                $cfsslUri
            );

            return new DataResponse([]);
        } catch (\Exception $exception) {
            return $this->handleErrors($exception);
        }
    }

    /**
     * @NoCSRFRequired
     *
     * @todo remove NoCSRFRequired
     */
    public function loadCertificate(): DataResponse
    {
        try {
            $certificate = $this->service->loadKeys();

            return new DataResponse($certificate);
        } catch (\Exception $exception) {
            return $this->handleErrors($exception);
        }
    }
}
