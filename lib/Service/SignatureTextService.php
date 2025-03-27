<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use DateTimeInterface;
use OCA\Libresign\Exception\LibresignException;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IL10N;
use Sabre\DAV\UUIDUtil;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

class SignatureTextService {
	public function __construct(
		private IAppConfig $appConfig,
		private IL10N $l10n,
	) {
	}

	/**
	 * @return array{parsed: string, fontSize: float}
	 * @throws LibresignException
	 */
	public function save(string $template, float $fontSize = 6): array {
		if ($fontSize > 30 || $fontSize < 0.1) {
			// TRANSLATORS This message refers to the font size used in the text
			// that is used together or to replace a person's handwritten
			// signature in the signed PDF. The user must enter a numeric value
			// within the accepted range.
			throw new LibresignException($this->l10n->t('Invalid font size. The value must be between %.1f and %.0f.', [0.1, 30]));
		}
		$template = trim($template);
		$this->appConfig->setAppValueString('signature_text_template', $template);
		$this->appConfig->setAppValueFloat('signature_font_size', $fontSize);
		return $this->parse($template);
	}

	/**
	 * @return array{parsed: string, fontSize: float}
	 * @throws LibresignException
	 */
	public function parse(string $template = '', array $context = []): array {
		if (empty($template)) {
			$template = $this->appConfig->getAppValueString('signature_text_template');
		}
		if (empty($context)) {
			$context = [
				'SignerName' => 'John Doe',
				'DocumentUUID' => UUIDUtil::getUUID(),
				'IssuerCommonName' => 'Acme Cooperative',
				'LocalSignerSignatureDate' => (new \DateTime())->format(DateTimeInterface::ATOM),
				'ServerSignatureDate' => (new \DateTime())->format(DateTimeInterface::ATOM),
			];
		}
		try {
			$twigEnvironment = new Environment(
				new FilesystemLoader(),
			);
			$template = $twigEnvironment
				->createTemplate($template)
				->render($context);
			$fontSize = $this->appConfig->getAppValueFloat('signature_font_size', 6);
			return [
				'parsed' => $template,
				'fontSize' => $fontSize,
			];
		} catch (SyntaxError $e) {
			throw new LibresignException((string)preg_replace('/in "[^"]+" at line \d+/', '', $e->getMessage()));
		}
	}

	public function getFontSize(): float {
		return $this->appConfig->getAppValueFloat('signature_font_size', 6);
	}
}
