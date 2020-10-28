<?php

namespace OCA\Signer\Controller;

use OCA\Signer\AppInfo\Application;
use OCA\Signer\Exception\SignerException;
use OCA\Signer\Service\SignerService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class SignerController extends Controller
{
    use HandleErrorsTrait;
    use HandleParamsTrait;

    /** @var SignerService */
    private $service;

    /** @var string */
    private $userId;

    public function __construct(
        IRequest $request,
        SignerService $service,
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
