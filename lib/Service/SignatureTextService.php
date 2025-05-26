<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use DateTimeInterface;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCP\IAppConfig;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Sabre\DAV\UUIDUtil;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

class SignatureTextService {
	public const TEMPLATE_DEFAULT_FONT_SIZE = 10;
	public const SIGNATURE_DEFAULT_FONT_SIZE = 20;
	public const FONT_SIZE_MINIMUM = 0.1;
	public const FRONT_SIZE_MAX = 30;
	public const DEFAULT_SIGNATURE_WIDTH = 350;
	public const DEFAULT_SIGNATURE_HEIGHT = 100;
	public function __construct(
		private IAppConfig $appConfig,
		private IL10N $l10n,
		private IDateTimeZone $dateTimeZone,
		private IRequest $request,
		private IUserSession $userSession,
		protected LoggerInterface $logger,
	) {
	}

	/**
	 * @return array{template: string, parsed: string, templateFontSize: float, signatureFontSize: float, signatureWidth: float, signatureHeight: float, renderMode: string}
	 * @throws LibresignException
	 */
	public function save(
		string $template,
		float $templateFontSize = self::TEMPLATE_DEFAULT_FONT_SIZE,
		float $signatureFontSize = self::SIGNATURE_DEFAULT_FONT_SIZE,
		float $signatureWidth = self::DEFAULT_SIGNATURE_WIDTH,
		float $signatureHeight = self::DEFAULT_SIGNATURE_HEIGHT,
		string $renderMode = SignerElementsService::RENDER_MODE_DEFAULT,
	): array {
		if ($templateFontSize > self::FRONT_SIZE_MAX || $templateFontSize < self::FONT_SIZE_MINIMUM) {
			// TRANSLATORS This message refers to the font size used in the text
			// that is used together or to replace a person's handwritten
			// signature in the signed PDF. The user must enter a numeric value
			// within the accepted range.
			throw new LibresignException($this->l10n->t('Invalid template font size. The value must be between %.1f and %.0f.', [self::FONT_SIZE_MINIMUM, self::FRONT_SIZE_MAX]));
		}
		if ($signatureFontSize > self::FRONT_SIZE_MAX || $signatureFontSize < self::FONT_SIZE_MINIMUM) {
			// TRANSLATORS This message refers to the font size used in the text
			// that is used together or to replace a person's handwritten
			// signature in the signed PDF. The user must enter a numeric value
			// within the accepted range.
			throw new LibresignException($this->l10n->t('Invalid signature font size. The value must be between %.1f and %.0f.', [self::FONT_SIZE_MINIMUM, self::FRONT_SIZE_MAX]));
		}
		$template = trim($template);
		$template = preg_replace(
			[
				'/>\s+</',
				'/<br\s*\/?>/i',
				'/<p[^>]*>/i',
				'/<\/p>/i',
			],
			[
				'><',
				"\n",
				'',
				"\n"
			],
			$template
		);
		$template = strip_tags((string)$template);
		$template = trim($template);
		$template = html_entity_decode($template);
		$this->appConfig->setValueString(Application::APP_ID, 'signature_text_template', $template);
		$this->appConfig->setValueFloat(Application::APP_ID, 'signature_width', $signatureWidth);
		$this->appConfig->setValueFloat(Application::APP_ID, 'signature_height', $signatureHeight);
		$this->appConfig->setValueFloat(Application::APP_ID, 'template_font_size', $templateFontSize);
		$this->appConfig->setValueFloat(Application::APP_ID, 'signature_font_size', $signatureFontSize);
		$this->appConfig->setValueString(Application::APP_ID, 'signature_render_mode', $renderMode);
		return $this->parse($template);
	}

	/**
	 * @return array{template: string, parsed: string, templateFontSize: float, signatureFontSize: float, signatureWidth: float, signatureHeight: float, renderMode: string}
	 * @throws LibresignException
	 */
	public function parse(string $template = '', array $context = []): array {
		$templateFontSize = $this->getTemplateFontSize();
		$signatureFontSize = $this->getSignatureFontSize();
		$signatureWidth = $this->getFullSignatureWidth();
		$signatureHeight = $this->getFullSignatureHeight();
		$renderMode = $this->getRenderMode();
		if (empty($template)) {
			$template = $this->getTemplate();
		}
		if (empty($template)) {
			return [
				'parsed' => '',
				'template' => $template,
				'templateFontSize' => $templateFontSize,
				'signatureFontSize' => $signatureFontSize,
				'signatureWidth' => $signatureWidth,
				'signatureHeight' => $signatureHeight,
				'renderMode' => $renderMode,
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
				'SignerCommonName' => $this->userSession?->getUser()?->getDisplayName() ?? 'John Doe',
				'SignerEmail' => $this->userSession?->getUser()?->getEMailAddress() ?? 'john.doe@libresign.coop',
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
				'templateFontSize' => $templateFontSize,
				'signatureFontSize' => $signatureFontSize,
				'signatureWidth' => $signatureWidth,
				'signatureHeight' => $signatureHeight,
				'renderMode' => $renderMode,
			];
		} catch (SyntaxError $e) {
			throw new LibresignException((string)preg_replace('/in "[^"]+" at line \d+/', '', $e->getMessage()));
		}
	}

	public function getTemplate(): string {
		if ($this->appConfig->hasKey(Application::APP_ID, 'signature_text_template')) {
			return $this->appConfig->getValueString(Application::APP_ID, 'signature_text_template');
		}
		return $this->getDefaultTemplate();
	}

	public function getAvailableVariables(): array {
		$list = [
			'{{DocumentUUID}}' => $this->l10n->t('Unique identifier of the signed document'),
			'{{IssuerCommonName}}' => $this->l10n->t('Name of the certificate issuer used for the signature.'),
			'{{LocalSignerSignatureDateOnly}}' => $this->l10n->t('Date when the signer sent the request to sign (without time, in their local time zone).'),
			'{{LocalSignerSignatureDateTime}}' => $this->l10n->t('Date and time when the signer sent the request to sign (in their local time zone).'),
			'{{LocalSignerTimezone}}' => $this->l10n->t('Time zone of signer when sent the request to sign (in their local time zone).'),
			'{{ServerSignatureDate}}' => $this->l10n->t('Date and time when the signature was applied on the server. Cannot be formatted using Twig.'),
			'{{SignerCommonName}}' => $this->l10n->t('Common Name (CN) used to identify the document signer.'),
			'{{SignerEmail}}' => $this->l10n->t('The signer\'s email is optional and can be left blank.'),
			'{{SignerIdentifier}}' => $this->l10n->t('Unique information used to identify the signer (such as email, phone number, or username).'),
		];
		$collectMetadata = $this->appConfig->getValueBool(Application::APP_ID, 'collect_metadata', false);
		if ($collectMetadata) {
			$list['{{SignerIP}}'] = $this->l10n->t('IP address of the person who signed the document.');
			$list['{{SignerUserAgent}}'] = $this->l10n->t('Browser and device information of the person who signed the document.');
		}
		return $list;
	}

	public function signerNameImage(
		string $text,
		int $width,
		int $height,
		string $align = 'center',
		float $fontSize = 0,
		bool $isDarkTheme = false,
		float $scale = 5,
	): string {
		$width *= $scale;
		$height *= $scale;

		$image = new Imagick();
		$image->setResolution(600, 600);
		$image->newImage((int)$width, (int)$height, new ImagickPixel('transparent'));
		$image->setImageFormat('png');

		$draw = new ImagickDraw();
		$fonts = Imagick::queryFonts();
		if ($fonts) {
			$draw->setFont($fonts[0]);
		} else {
			$fallbackFond = __DIR__ . '/../../vendor/mpdf/mpdf/ttfonts/DejaVuSerifCondensed.ttf';
			if (!file_exists($fallbackFond)) {
				$this->logger->error('No fonts available at system, and fallback font not found: ' . $fallbackFond);
				throw new LibresignException('No fonts available at system, and fallback font not found: ' . $fallbackFond);
			}
			$draw->setFont(__DIR__ . '/../../vendor/mpdf/mpdf/ttfonts/DejaVuSerifCondensed.ttf');
		}
		if (!$fontSize) {
			$fontSize = $this->getSignatureFontSize();
		}
		$fontSize *= $scale;
		$draw->setFontSize($fontSize);
		$draw->setFillColor(new ImagickPixel($isDarkTheme ? 'white' : 'black'));
		$align = match ($align) {
			'left' => Imagick::ALIGN_LEFT,
			'center' => Imagick::ALIGN_CENTER,
			'right' => Imagick::ALIGN_RIGHT,
		};
		$draw->setTextAlignment($align);

		$maxCharsPerLine = $this->splitAndGetLongestHalfLength($text);
		$wrappedText = wordwrap($text, $maxCharsPerLine, "\n", true);

		$textMetrics = $image->queryFontMetrics($draw, $wrappedText);
		$lineCount = substr_count($wrappedText, "\n") + 1;
		$y = $this->getCenteredBaselineY($height, $lineCount, $textMetrics['textHeight'], $textMetrics['ascender'], $textMetrics['descender']);

		$x = match ($align) {
			Imagick::ALIGN_LEFT => 0,
			Imagick::ALIGN_CENTER => $width / 2,
			Imagick::ALIGN_RIGHT => $width,
		};

		$image->annotateImage($draw, $x, $y, 0, $wrappedText);

		$blob = $image->getImagesBlob();
		$image->destroy();

		return $blob;
	}

	private function getCenteredBaselineY(
		float $canvasHeight,
		int $lineCount,
		float $lineHeight,
		float $ascender,
		float $descender,
	): float {
		$centerY = $canvasHeight / 2;
		$textBlockHeight = $lineHeight * $lineCount;
		$visualCenterOffset = ($ascender + $descender) / 2;

		return $centerY - ($textBlockHeight / 2) + $lineHeight - $visualCenterOffset;
	}

	private function splitAndGetLongestHalfLength(string $text): int {
		$text = trim($text);
		$length = mb_strlen($text);

		if ($length === 0) {
			return 0;
		}

		$middle = (int)($length / 2);
		$results = [];

		foreach (['backward' => -1, 'forward' => 1] as $directionName => $direction) {
			$index = $middle;

			while (
				$index >= 0 &&
				$index < $length &&
				mb_substr($text, $index, 1) !== ' '
			) {
				$index += $direction;
			}

			if (
				$index > 0 &&
				$index < $length &&
				mb_substr($text, $index, 1) === ' '
			) {
				$first = mb_substr($text, 0, $index);
				$second = mb_substr($text, $index + 1);
				$results[] = max(mb_strlen($first), mb_strlen($second));
			}
		}

		return !empty($results) ? max($results) : $length;
	}

	public function getDefaultTemplate(): string {
		$collectMetadata = $this->appConfig->getValueBool(Application::APP_ID, 'collect_metadata', false);
		if ($collectMetadata) {
			return $this->l10n->t(<<<TEMPLATE
				Signed with LibreSign
				{{SignerCommonName}}
				Issuer: {{IssuerCommonName}}
				Date: {{ServerSignatureDate}}
				IP: {{SignerIP}}
				User agent: {{SignerUserAgent}}
				TEMPLATE
			);
		}
		return $this->l10n->t(<<<TEMPLATE
			Signed with LibreSign
			{{SignerCommonName}}
			Issuer: {{IssuerCommonName}}
			Date: {{ServerSignatureDate}}
			TEMPLATE
		);
	}

	public function getFullSignatureWidth(): float {
		return $this->appConfig->getValueFloat(Application::APP_ID, 'signature_width', self::DEFAULT_SIGNATURE_WIDTH);
	}

	public function getFullSignatureHeight(): float {
		return $this->appConfig->getValueFloat(Application::APP_ID, 'signature_height', self::DEFAULT_SIGNATURE_HEIGHT);
	}

	public function getSignatureWidth(): float {
		$current = $this->appConfig->getValueFloat(Application::APP_ID, 'signature_width', self::DEFAULT_SIGNATURE_WIDTH);
		if ($this->getRenderMode() === 'GRAPHIC_ONLY' || !$this->getTemplate()) {
			return $current;
		}
		return $current / 2;
	}

	public function getSignatureHeight(): float {
		return $this->appConfig->getValueFloat(Application::APP_ID, 'signature_height', self::DEFAULT_SIGNATURE_HEIGHT);
	}

	public function getTemplateFontSize(): float {
		$collectMetadata = $this->appConfig->getValueBool(Application::APP_ID, 'collect_metadata', false);
		if ($collectMetadata) {
			return $this->appConfig->getValueFloat(Application::APP_ID, 'template_font_size', self::TEMPLATE_DEFAULT_FONT_SIZE - 1);
		}
		return $this->appConfig->getValueFloat(Application::APP_ID, 'template_font_size', self::TEMPLATE_DEFAULT_FONT_SIZE);
	}

	public function getDefaultTemplateFontSize(): float {
		$collectMetadata = $this->appConfig->getValueBool(Application::APP_ID, 'collect_metadata', false);
		if ($collectMetadata) {
			return self::TEMPLATE_DEFAULT_FONT_SIZE - 0.2;
		}
		return self::TEMPLATE_DEFAULT_FONT_SIZE;
	}

	public function getSignatureFontSize(): float {
		return $this->appConfig->getValueFloat(Application::APP_ID, 'signature_font_size', self::SIGNATURE_DEFAULT_FONT_SIZE);
	}

	public function getRenderMode(): string {
		return $this->appConfig->getValueString(Application::APP_ID, 'signature_render_mode', SignerElementsService::RENDER_MODE_DEFAULT);
	}

	public function isEnabled(): bool {
		return !empty($this->getTemplate());
	}
}
