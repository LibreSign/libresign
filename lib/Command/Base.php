<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command;

use OC\Core\Command\Base as CommandBase;
use OCA\Libresign\Service\Install\InstallService;
use Psr\Log\LoggerInterface;

class Base extends CommandBase {
	/** @var InstallService */
	public $installService;

	/** @var LoggerInterface */
	protected $logger;

	public function __construct(
		InstallService $installService,
		LoggerInterface $logger
	) {
		parent::__construct();
		$this->installService = $installService;
		$this->logger = $logger;
	}

	protected function installJava(): void {
		$this->installService->installJava();
	}

	protected function uninstallJava(): void {
		$this->installService->uninstallJava();
	}

	protected function installJSignPdf(): void {
		$this->installService->installJSignPdf();
	}

	protected function uninstallJSignPdf(): void {
		$this->installService->uninstallJSignPdf();
	}

	protected function installPdftk(): void {
		$this->installService->installPdftk();
	}

	protected function uninstallPdftk(): void {
		$this->installService->uninstallPdftk();
	}

	protected function installCfssl(): void {
		$this->installService->installCfssl();
	}

	protected function uninstallCfssl(): void {
		$this->installService->uninstallCfssl();
	}
}
