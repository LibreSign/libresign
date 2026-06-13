<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Font;

use OCA\Libresign\AppInfo\Application;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class FontConfigService {
	public const string DEFAULT_FONT_FAMILY = 'dejavusanscondensed';
	private const array ALLOWED_FONT_EXTENSIONS = ['ttf', 'otf', 'ttc'];
	private const array CONFIGURATION_GROUPS = [
		[
			'family' => 'template_font_family',
			'directory' => 'template_font_dir',
			'regular' => 'template_font_regular',
			'bold' => 'template_font_bold',
			'italic' => 'template_font_italic',
			'boldItalic' => 'template_font_bold_italic',
		],
		[
			'family' => 'footer_font_family',
			'directory' => 'footer_font_dir',
			'regular' => 'footer_font_regular',
			'bold' => 'footer_font_bold',
			'italic' => 'footer_font_italic',
			'boldItalic' => 'footer_font_bold_italic',
		],
	];

	public function __construct(
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}

	private bool $configuredTemplateFontResolved = false;
	private ?FontDefinition $configuredTemplateFont = null;

	public function getActiveFontFamily(): string {
		return $this->getConfiguredTemplateFont()?->getFamily() ?? self::DEFAULT_FONT_FAMILY;
	}

	public function getConfiguredTemplateFont(): ?FontDefinition {
		if ($this->configuredTemplateFontResolved) {
			return $this->configuredTemplateFont;
		}

		foreach (self::CONFIGURATION_GROUPS as $configKeys) {
			$fontDefinition = $this->resolveFontDefinition($configKeys);
			if ($fontDefinition !== null) {
				$this->configuredTemplateFont = $fontDefinition;
				$this->configuredTemplateFontResolved = true;

				return $this->configuredTemplateFont;
			}
		}

		$this->configuredTemplateFontResolved = true;

		return null;
	}

	/**
	 * @param array{family: string, directory: string, regular: string, bold: string, italic: string, boldItalic: string} $configKeys
	 */
	private function resolveFontDefinition(array $configKeys): ?FontDefinition {
		$fontFamily = $this->normalizeFontFamily(
			$this->appConfig->getValueString(Application::APP_ID, $configKeys['family'], '')
		);
		$fontDirectoryConfig = trim($this->appConfig->getValueString(Application::APP_ID, $configKeys['directory'], ''));
		$fontDirectory = $fontDirectoryConfig !== '' ? realpath($fontDirectoryConfig) : false;

		if ($fontFamily === '' || $fontDirectory === false || !is_dir($fontDirectory)) {
			if ($this->configurationGroupHasAnyValue($configKeys)) {
				$this->logger->warning('Ignoring invalid custom template font configuration: missing valid directory or family', [
					'familyKey' => $configKeys['family'],
					'directoryKey' => $configKeys['directory'],
				]);
			}

			return null;
		}

		$regular = $this->validateConfiguredFontFile(
			$fontDirectory,
			trim($this->appConfig->getValueString(Application::APP_ID, $configKeys['regular'], '')),
			$configKeys['regular'],
		);
		if ($regular === null) {
			return null;
		}

		$bold = $this->validateConfiguredOptionalFontFile(
			$fontDirectory,
			trim($this->appConfig->getValueString(Application::APP_ID, $configKeys['bold'], '')),
			$configKeys['bold'],
		);
		$italic = $this->validateConfiguredOptionalFontFile(
			$fontDirectory,
			trim($this->appConfig->getValueString(Application::APP_ID, $configKeys['italic'], '')),
			$configKeys['italic'],
		);
		$boldItalic = $this->validateConfiguredOptionalFontFile(
			$fontDirectory,
			trim($this->appConfig->getValueString(Application::APP_ID, $configKeys['boldItalic'], '')),
			$configKeys['boldItalic'],
		);

		return new FontDefinition(
			$fontFamily,
			$fontDirectory,
			$regular,
			$bold ?? $regular,
			$italic ?? $regular,
			$boldItalic ?? ($bold ?? $regular),
		);
	}

	private function normalizeFontFamily(string $fontFamily): string {
		$normalizedFontFamily = strtolower(trim($fontFamily));
		$normalizedFontFamily = preg_replace('/[^a-z0-9]+/', '', $normalizedFontFamily);

		return is_string($normalizedFontFamily) ? $normalizedFontFamily : '';
	}

	/**
	 * @param array{family: string, directory: string, regular: string, bold: string, italic: string, boldItalic: string} $configKeys
	 */
	private function configurationGroupHasAnyValue(array $configKeys): bool {
		foreach ($configKeys as $configKey) {
			if (trim($this->appConfig->getValueString(Application::APP_ID, $configKey, '')) !== '') {
				return true;
			}
		}

		return false;
	}

	private function validateConfiguredOptionalFontFile(string $fontDirectory, string $configuredFile, string $configKey): ?string {
		if ($configuredFile === '') {
			return null;
		}

		return $this->validateConfiguredFontFile($fontDirectory, $configuredFile, $configKey, false);
	}

	private function validateConfiguredFontFile(string $fontDirectory, string $configuredFile, string $configKey, bool $required = true): ?string {
		$normalizedFile = str_replace('\\', '/', trim($configuredFile));
		if ($normalizedFile === '') {
			if ($required) {
				$this->logger->warning('Ignoring invalid custom template font configuration: required font file is missing', [
					'configKey' => $configKey,
				]);
			}

			return null;
		}

		if (
			$this->isAbsolutePath($normalizedFile)
			|| preg_match('~(^|/)\.\.(/|$)~', $normalizedFile) === 1
			|| preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\//', $normalizedFile) === 1
		) {
			$this->logger->warning('Ignoring invalid custom template font configuration: font file path is not allowed', [
				'configKey' => $configKey,
			]);

			return null;
		}

		$extension = strtolower((string)pathinfo($normalizedFile, PATHINFO_EXTENSION));
		if (!in_array($extension, self::ALLOWED_FONT_EXTENSIONS, true)) {
			$this->logger->warning('Ignoring invalid custom template font configuration: font file extension is not supported', [
				'configKey' => $configKey,
			]);

			return null;
		}

		$fontPath = $fontDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedFile);
		$realFontPath = realpath($fontPath);
		if ($realFontPath === false || !is_file($realFontPath)) {
			$this->logger->warning('Ignoring invalid custom template font configuration: configured font file does not exist', [
				'configKey' => $configKey,
			]);

			return null;
		}

		$normalizedDirectory = rtrim($fontDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		if (!str_starts_with($realFontPath, $normalizedDirectory)) {
			$this->logger->warning('Ignoring invalid custom template font configuration: configured font file escapes the configured directory', [
				'configKey' => $configKey,
			]);

			return null;
		}

		return $normalizedFile;
	}

	private function isAbsolutePath(string $path): bool {
		return str_starts_with($path, '/')
			|| preg_match('~^[a-zA-Z]:[\\/]~', $path) === 1;
	}
}
