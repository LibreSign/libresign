<?php

namespace OCA\Signer\Controller;

use OCA\Signer\Exception\SignerException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

trait HandleErrorsTrait
{
    protected function handleErrors(\Exception $exception): DataResponse
    {
        if ($exception instanceof SignerException) {
            return new DataResponse($exception->jsonSerialize(), $exception->getCode());
        }

        return new DataResponse(
            ['message' => $exception->getMessage()],
            Http::STATUS_INTERNAL_SERVER_ERROR
        );
    }
}
