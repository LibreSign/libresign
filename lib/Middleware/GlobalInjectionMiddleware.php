<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Middleware;

use OCA\Files\Controller\ViewController;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;

class GlobalInjectionMiddleware extends Middleware {
	public function afterController(Controller $controller, string $methodName, Response $response) {
		if ($controller instanceof ViewController) {
			$policy = new ContentSecurityPolicy();
			$policy->addAllowedFrameDomain("'self'");
			$response->setContentSecurityPolicy($policy);
		}
		return $response;
	}
}
