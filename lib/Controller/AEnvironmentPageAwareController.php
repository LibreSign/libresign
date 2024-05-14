<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\SignFileService;
use OCP\AppFramework\Controller;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

abstract class AEnvironmentPageAwareController extends Controller implements ISignatureUuid {
	use LibresignTrait;

	public function __construct(
		IRequest $request,
		protected SignFileService $signFileService,
		protected IL10N $l10n,
		protected IUserSession $userSession,
	) {
		parent::__construct(Application::APP_ID, $request);
	}
}
