<?php

namespace OCA\Signer\Controller;

use OCA\Signer\AppInfo\Application;
use OCA\Signer\Service\SignatureService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class SignatureController extends Controller
{
    use HandleErrorsTrait;
    use HandleParamsTrait;

    /** @var SignatureService */
    private $service;

    /** @var string */
    private $userId;

    public function __construct(
        IRequest $request,
        SignatureService $service,
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
    public function generate(
        string $commonName = null,
        string $hosts = null,
        string $country = null,
        string $organization = null,
        string $organizationUnit = null,
        string $path = null,
        string $password = null
    ): DataResponse {
        try {
            $this->checkParams([
                'commonName' => $commonName,
                'hosts' => $hosts,
                'country' => $country,
                'organization' => $organization,
                'organizationUnit' => $organizationUnit,
                'path' => $path,
                'password' => $password,
            ]);
            $hosts = explode(',', $hosts);
            $signaturePath = $this->service->generate(
                $commonName,
                $hosts,
                $country,
                $organization,
                $organizationUnit,
                $path,
                $password
            );

            return new DataResponse(['signature' => $signaturePath]);
        } catch (\Exception $exception) {
            return $this->handleErrors($exception);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @todo remove NoCSRFRequired
     */
    public function check(){
    try{
        $checkData = $this->service->check();

        return new DataResponse($checkData);
    } catch (\Exception $exception) {
        return $this->handleErrors($exception);
    }
    }
}
