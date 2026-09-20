<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Install;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\InvalidSignatureException;
use OCP\Files\NotFoundException;
use OCP\IAppConfig;
use OCP\IConfig;

class SetupInstallPathResolver {
	private string $instanceId;

	public function __construct(
		private IConfig $config,
		private IAppConfig $appConfig,
		private DependencyStorage $dependencyStorage,
	) {
		$this->instanceId = (string)$this->config->getSystemValue('instanceid');
	}

	public function resolve(InstallTarget $target, string $resource): string {
		$installPath = match ($resource) {
			'java' => $this->resolveJava($target),
			'jsignpdf' => $this->resolveDirectoryResource(
				$target,
				'jsignpdf_path',
				'jsignpdf',
				static fn (string $path): string => dirname($path),
				'JSignPdf',
			),
			'pdftk' => $this->resolveDirectoryResource(
				$target,
				'pdftk_path',
				'pdftk',
				static fn (string $path): string => substr($path, 0, -strlen('/pdftk.jar')),
				'PDFtk',
			),
			'cfssl' => $this->resolveDirectoryResource(
				$target,
				'cfssl_bin',
				'cfssl',
				static fn (string $path): string => substr($path, 0, -strlen('/cfssl')),
				'CFSSL',
			),
			default => throw new InvalidSignatureException(sprintf('Unsupported setup resource "%s".', $resource)),
		};

		return $this->normalizeArchitecture($installPath, $target);
	}

	private function resolveJava(InstallTarget $target): string {
		$path = $this->appConfig->getValueString(Application::APP_ID, 'java_path');
		if ($path === '') {
			return $this->resolveAppDataFolder(
				$target->architecture() . '/' . $target->distro() . '/java',
				'Java',
			);
		}

		$installPath = substr($path, 0, -strlen('/bin/java'));
		$expected = $this->instanceId
			. '/libresign/'
			. $target->architecture()
			. '/'
			. $target->distro()
			. '/java';

		if (str_contains($installPath, $expected)) {
			return $installPath;
		}

		return (string)preg_replace(
			'/'
			. preg_quote($this->instanceId, '/')
			. '\/libresign\/([^\/]+)\/([^\/]+)\/java/i',
			$expected,
			$installPath,
		);
	}

	/**
	 * @param callable(string): string $configuredPathToDirectory
	 */
	private function resolveDirectoryResource(
		InstallTarget $target,
		string $configKey,
		string $resource,
		callable $configuredPathToDirectory,
		string $displayName,
	): string {
		$path = $this->appConfig->getValueString(Application::APP_ID, $configKey);
		if ($path === '') {
			return $this->resolveAppDataFolder(
				$target->architecture() . '/' . $resource,
				$displayName,
			);
		}
		return $configuredPathToDirectory($path);
	}

	private function resolveAppDataFolder(string $relativePath, string $displayName): string {
		try {
			$folder = $this->dependencyStorage->rootFolder()->getFolder($relativePath);
			$path = $this->dependencyStorage->pathOfFolder($folder);
			if (is_dir($path)) {
				return $path;
			}
		} catch (NotFoundException) {
		}
		throw new InvalidSignatureException($displayName . ' path not found at app config.');
	}

	private function normalizeArchitecture(string $installPath, InstallTarget $target): string {
		if (str_contains($installPath, $target->architecture())) {
			return $installPath;
		}

		return (string)preg_replace(
			'/'
			. preg_quote($this->instanceId, '/')
			. '\/libresign\/([^\/]+)/i',
			$this->instanceId . '/libresign/' . $target->architecture(),
			$installPath,
		);
	}
}
