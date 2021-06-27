<?php

namespace OCA\Libresign\Controller;

use OCA\Libresign\Helper\JSActions;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

/**
 * @deprecated 2.4.0
 */
class SignFileDeprecatedController extends SignFileController {
	/**
	 * Request signature
	 *
	 * Request that a file be signed by a group of people
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @deprecated 2.4.0 Repaced by POST /sign/register
	 * @codeCoverageIgnore
	 * @param array $file
	 * @param array $users
	 * @param string $name
	 * @param string|null $callback
	 * @return JSONResponse
	 */
	public function requestSign(array $file, array $users, string $name, ?string $callback = null) {
		return parent::requestSign($file, $users, $name, $callback);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @codeCoverageIgnore
	 * @deprecated 2.4.0 Repaced by PATCH /sign/register
	 *
	 * @return JSONResponse
	 */
	public function updateSign(string $uuid, array $users) {
		return parent::updateSign($uuid, $users);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @codeCoverageIgnore
	 * @deprecated 2.4.0 Repaced by DELETE /sign/register
	 *
	 * @return JSONResponse
	 */
	public function removeSign(string $uuid, array $users) {
		return parent::removeSign($uuid, $users);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @todo remove NoCSRFRequired
	 * @deprecated 2.4.0 Replaced by POST /sign/register
	 */
	public function sign(
		string $inputFilePath = null,
		string $outputFolderPath = null,
		string $certificatePath = null,
		string $password = null
	): JSONResponse {
		try {
			$this->checkParams([
				'inputFilePath' => $inputFilePath,
				'outputFolderPath' => $outputFolderPath,
				'certificatePath' => $certificatePath,
				'password' => $password,
			]);

			$fileSigned = $this->signFile->signDeprecated($inputFilePath, $outputFolderPath, $certificatePath, $password);

			return new JSONResponse(
				['fileSigned' => $fileSigned->getInternalPath()],
				HTTP::STATUS_OK
			);
		} catch (\Exception $exception) {
			return new JSONResponse(
				[
					'action' => JSActions::ACTION_DO_NOTHING,
					'errors' => [$this->l10n->t($exception->getMessage())]
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
	}
}
