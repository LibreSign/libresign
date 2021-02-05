<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\Exception\LibresignException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

trait HandleErrorsTrait {
	protected function handleErrors(\Exception $exception): DataResponse {
		if ($exception instanceof LibresignException) {
			return new DataResponse($exception->jsonSerialize(), $exception->getCode());
		}

		return new DataResponse(
			['message' => $exception->getMessage()],
			Http::STATUS_INTERNAL_SERVER_ERROR
		);
	}
}
