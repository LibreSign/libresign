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
use Sabre\DAV\UUIDUtil;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

class SignatureTextService {
	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * @return array{parsed: string, fontSize: float}
	 * @throws LibresignException
	 */
	public function save(string $template, float $fontSize = 6): array {
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
				'SignatureDate' => (new \DateTime())->format(DateTimeInterface::ATOM)
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
