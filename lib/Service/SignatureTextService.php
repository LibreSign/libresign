<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use DateTimeInterface;
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
	public function save(string $template): string {
		$this->appConfig->setAppValueString('signature_text_template', $template);
		return $this->parse($template);
	}

	public function parse(string $template, array $context = []): string {
		if (empty($template)) {
			$template = $this->appConfig->getAppValueString('signature_text_template');
		}
		if (empty($context)) {
			$context = [
				'SignerName' => 'John Doe',
				'DocumentUUID' => UUIDUtil::getUUID(),
				'CommonName' => 'Acme Cooperative',
				'SignatureDate' => (new \DateTime())->format(DateTimeInterface::ATOM)
			];
		}
		try {
			$twigEnvironment = new Environment(
				new FilesystemLoader(),
			);
			return $twigEnvironment
				->createTemplate($template)
				->render($context);
		} catch (SyntaxError $e) {
			return (string) preg_replace('/in "[^"]+" at line \d+/', '', $e->getMessage());
		}
	}
}
