<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\RequestMetadataService;
use OCA\Libresign\Service\SignerGeolocation\SignerGeolocationMetadataValidator;
use OCA\Libresign\Service\SignerIpGeolocation\SignerIpGeolocationPolicyService;
use OCP\IRequest;

class SigningMetadataProvider {
	public function __construct(
		private IRequest $request,
		private RequestMetadataService $requestMetadataService,
		private SignerGeolocationMetadataValidator $deviceGeolocationValidator,
		private SignerIpGeolocationPolicyService $ipGeolocationService,
	) {
	}

	/**
	 * @param array<string, mixed> $deviceGeolocation
	 * @return array<string, mixed>
	 */
	public function collect(File $file, SignRequest $signRequest, array $deviceGeolocation = []): array {
		$metadata = $this->requestMetadataService->collectMetadata();
		$deviceLocation = $this->deviceGeolocationValidator->normalize(
			$deviceGeolocation === [] ? null : $deviceGeolocation,
		);
		$this->deviceGeolocationValidator->validateSubmission($signRequest, $deviceLocation);
		if ($deviceLocation !== null) {
			$metadata = $this->deviceGeolocationValidator->mergeDeviceIntoMetadata($metadata, $deviceLocation);
		}

		$ipLocation = $this->ipGeolocationService->collectMetadata($file, $this->request);
		if ($ipLocation !== null) {
			$metadata = $this->ipGeolocationService->mergeIntoMetadata($metadata, $ipLocation);
		}

		return $metadata;
	}
}
