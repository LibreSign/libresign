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
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IRequest;
use Sabre\DAV\UUIDUtil;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

class SignatureTextService {
	public function __construct(
		private IAppConfig $appConfig,
		private IL10N $l10n,
		private IDateTimeZone $dateTimeZone,
		private IRequest $request,
	) {
	}

	/**
	 * @return array{template: string, parsed: string, fontSize: float, renderMode: string}
	 * @throws LibresignException
	 */
	public function save(
		string $template,
		float $fontSize = 6,
		string $renderMode = 'GRAPHIC_AND_DESCRIPTION',
	): array {
		if ($fontSize > 30 || $fontSize < 0.1) {
			// TRANSLATORS This message refers to the font size used in the text
			// that is used together or to replace a person's handwritten
			// signature in the signed PDF. The user must enter a numeric value
			// within the accepted range.
			throw new LibresignException($this->l10n->t('Invalid font size. The value must be between %.1f and %.0f.', [0.1, 30]));
		}
		$template = trim($template);
		$template = preg_replace('/>\s+</', '><', $template);
		$template = preg_replace('/<br\s*\/?>/i', "\n", $template);
		$template = preg_replace('/<p[^>]*>/i', '', $template);
		$template = preg_replace('/<\/p>/i', "\n", $template);
		$template = strip_tags($template);
		$template = trim($template);
		$template = html_entity_decode($template);
		$this->appConfig->setAppValueString('signature_text_template', $template);
		$this->appConfig->setAppValueFloat('signature_font_size', $fontSize);
		$this->appConfig->setAppValueString('signature_render_mode', $renderMode);
		return $this->parse($template);
	}

	/**
	 * @return array{template: string, parsed: string, fontSize: float}
	 * @throws LibresignException
	 */
	public function parse(string $template = '', array $context = []): array {
		$fontSize = $this->appConfig->getAppValueFloat('signature_font_size', $this->getDefaultFontSize());
		if (empty($template)) {
			$template = $this->appConfig->getAppValueString('signature_text_template');
		}
		if (empty($template)) {
			return [
				'parsed' => '',
				'template' => $template,
				'fontSize' => $fontSize,
			];
		}
		if (empty($context)) {
			$context = [
				'DocumentUUID' => UUIDUtil::getUUID(),
				'IssuerCommonName' => 'Acme Cooperative',
				'LocalSignerSignatureDateOnly' => (new \DateTime())->format('Y-m-d'),
				'LocalSignerSignatureDateTime' => (new \DateTime())->format(DateTimeInterface::ATOM),
				'LocalSignerTimezone' => $this->dateTimeZone->getTimeZone()->getName(),
				'ServerSignatureDate' => (new \DateTime())->format(DateTimeInterface::ATOM),
				'SignerIP' => $this->request->getRemoteAddress(),
				'SignerName' => 'John Doe',
				'SignerUserAgent' => $this->request->getHeader('User-Agent'),
			];
		}
		try {
			$twigEnvironment = new Environment(
				new FilesystemLoader(),
			);
			$parsed = $twigEnvironment
				->createTemplate($template)
				->render($context);
			return [
				'parsed' => $parsed,
				'template' => $template,
				'fontSize' => $fontSize,
			];
		} catch (SyntaxError $e) {
			throw new LibresignException((string)preg_replace('/in "[^"]+" at line \d+/', '', $e->getMessage()));
		}
	}

	public function getAvailableVariables(): array {
		$list = [
			'{{DocumentUUID}}' => $this->l10n->t('Unique identifier of the signed document'),
			'{{IssuerCommonName}}' => $this->l10n->t('Name of the certificate issuer used for the signature'),
			'{{LocalSignerSignatureDateOnly}}' => $this->l10n->t('Date when the signer sent the request to sign (without time, in their local time zone)'),
			'{{LocalSignerSignatureDateTime}}' => $this->l10n->t('Date and time when the signer send the request to sign (in their local time zone)'),
			'{{LocalSignerTimezone}}' => $this->l10n->t('Time zone of signer when send the request to sign (in their local time zone)'),
			'{{ServerSignatureDate}}' => $this->l10n->t('Date and time when the signature was applied on the server'),
			'{{SignerName}}' => $this->l10n->t('Name of the person signing'),
			'{{SignerIdentifier}}' => $this->l10n->t('Unique information used to identify the signer (such as email, phone number, or username).'),
		];
		$collectMetadata = $this->appConfig->getAppValueBool('collect_metadata', false);
		if ($collectMetadata) {
			$list['{{SignerIP}}'] = $this->l10n->t('IP address of the person who signed the document.');
			$list['{{SignerUserAgent}}'] = $this->l10n->t('Browser and device information of the person who signed the document.');
		}
		return $list;
	}

	public function getDefaultTemplate(): string {
		$collectMetadata = $this->appConfig->getAppValueBool('collect_metadata', false);
		if ($collectMetadata) {
			return $this->l10n->t(<<<TEMPLATE
				Signed with LibreSign
				{{SignerName}}
				Issuer: {{IssuerCommonName}}
				Date: {{ServerSignatureDate}}
				IP: {{SignerIP}}
				User agent: {{SignerUserAgent}}
				TEMPLATE
			);
		}
		return $this->l10n->t(<<<TEMPLATE
			Signed with LibreSign
			{{SignerName}}
			Issuer: {{IssuerCommonName}}
			Date: {{ServerSignatureDate}}
			TEMPLATE
		);
	}

	public function getRenderMode(): string {
		return $this->appConfig->getAppValueString('signature_render_mode', 'GRAPHIC_AND_DESCRIPTION');
	}

	public function getDefaultFontSize(): float {
		return 10;
	}
}
