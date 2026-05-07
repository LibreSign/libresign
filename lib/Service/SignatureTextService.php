<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use DateTimeInterface;
use Exception;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\CollectMetadata\CollectMetadataPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicy as SignatureTextPolicyProvider;
use OCA\Libresign\Vendor\Endroid\QrCode\Color\Color;
use OCA\Libresign\Vendor\Endroid\QrCode\Encoding\Encoding;
use OCA\Libresign\Vendor\Endroid\QrCode\ErrorCorrectionLevel;
use OCA\Libresign\Vendor\Endroid\QrCode\QrCode;
use OCA\Libresign\Vendor\Endroid\QrCode\RoundBlockSizeMode;
use OCA\Libresign\Vendor\Endroid\QrCode\Writer\PngWriter;
use OCA\Libresign\Vendor\Twig\Environment;
use OCA\Libresign\Vendor\Twig\Error\SyntaxError;
use OCA\Libresign\Vendor\Twig\Loader\FilesystemLoader;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Sabre\DAV\UUIDUtil;

class SignatureTextService {
	public const TEMPLATE_DEFAULT_FONT_SIZE = 10;
	public const SIGNATURE_DEFAULT_FONT_SIZE = 20;
	public const SIGNATURE_DIMENSION_MINIMUM = 1;
	public const FONT_SIZE_MINIMUM = 0.1;
	public const FRONT_SIZE_MAX = 30;
	public const DEFAULT_SIGNATURE_WIDTH = 350;
	public const DEFAULT_SIGNATURE_HEIGHT = 100;
	private const QRCODE_SIZE = 100;
	public function __construct(
		private IL10N $l10n,
		private IDateTimeZone $dateTimeZone,
		private IRequest $request,
		private IUserSession $userSession,
		private IURLGenerator $urlGenerator,
		protected LoggerInterface $logger,
		private PolicyService $policyService,
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
		if (
			!is_finite($signatureWidth)
			|| !is_finite($signatureHeight)
			|| $signatureWidth < self::SIGNATURE_DIMENSION_MINIMUM
			|| $signatureHeight < self::SIGNATURE_DIMENSION_MINIMUM
		) {
			// TRANSLATORS This message is shown when the visible signature box size
			// configured by the admin is invalid. "Signature box" is the rectangular
			// area reserved for the handwritten-style signature image in the signed
			// PDF. "Width" and "height" are its pixel dimensions. %.0f is the
			// minimum allowed value for each dimension.
			throw new LibresignException($this->l10n->t('Invalid signature box size. Width and height must be at least %.0f.', [self::SIGNATURE_DIMENSION_MINIMUM]));
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
		$this->policyService->saveSystem(SignatureTextPolicyProvider::KEY_TEMPLATE, $template);
		$this->policyService->saveSystem(SignatureTextPolicyProvider::KEY_SIGNATURE_WIDTH, $signatureWidth);
		$this->policyService->saveSystem(SignatureTextPolicyProvider::KEY_SIGNATURE_HEIGHT, $signatureHeight);
		$this->policyService->saveSystem(SignatureTextPolicyProvider::KEY_TEMPLATE_FONT_SIZE, $templateFontSize);
		$this->policyService->saveSystem(SignatureTextPolicyProvider::KEY_SIGNATURE_FONT_SIZE, $signatureFontSize);
		$this->policyService->saveSystem(SignatureTextPolicyProvider::KEY_RENDER_MODE, $renderMode);
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
			$date = new \DateTime('now', new \DateTimeZone('UTC'));
			$documentUuid = UUIDUtil::getUUID();
			$validationUrl = $this->buildValidationUrl($documentUuid);
			$context = [
				'DocumentUUID' => $documentUuid,
				'IssuerCommonName' => 'Acme Cooperative',
				'LocalSignerSignatureDateOnly' => ($date)->format('Y-m-d'),
				'LocalSignerSignatureDateTime' => ($date)->format(DateTimeInterface::ATOM),
				'LocalSignerTimezone' => $this->dateTimeZone->getTimeZone()->getName(),
				'ServerSignatureDate' => ($date)->format(DateTimeInterface::ATOM),
				'SignerIP' => $this->request->getRemoteAddress(),
				'SignerCommonName' => $this->userSession?->getUser()?->getDisplayName() ?? 'John Doe',
				'SignerEmail' => $this->userSession?->getUser()?->getEMailAddress() ?? 'john.doe@libresign.coop',
				'SignerUserAgent' => $this->request->getHeader('User-Agent'),
				'ValidationURL' => $validationUrl,
				'qrcode' => $this->getQrCodeImageBase64($validationUrl),
			];
		}

		if (!isset($context['ValidationURL']) && isset($context['DocumentUUID']) && is_string($context['DocumentUUID']) && $context['DocumentUUID'] !== '') {
			$context['ValidationURL'] = $this->buildValidationUrl($context['DocumentUUID']);
		}
		if (!isset($context['qrcode']) && isset($context['ValidationURL']) && is_string($context['ValidationURL'])) {
			$context['qrcode'] = $this->getQrCodeImageBase64($context['ValidationURL']);
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
		return (string)$this->policyService->resolve(SignatureTextPolicyProvider::KEY_TEMPLATE)->getEffectiveValue();
	}

	public function getAvailableVariables(): array {
		$list = [
			'{{DocumentUUID}}' => $this->l10n->t('Unique identifier of the signed document'),
			'{{IssuerCommonName}}' => $this->l10n->t('Name of the certificate issuer used for the signature.'),
			'{{LocalSignerSignatureDateOnly}}' => $this->l10n->t('Date when the signer sent the request to sign (without time, in their local time zone).'),
			'{{LocalSignerSignatureDateTime}}' => $this->l10n->t('Date and time when the signer sent the request to sign (in their local time zone).'),
			'{{LocalSignerTimezone}}' => $this->l10n->t('Time zone of signer when sent the request to sign (in their local time zone).'),
			'{{ServerSignatureDate}}' => $this->l10n->t('Date and time when the signature was applied on the server (ISO 8601 format). Can be formatted using the Twig date filter.'),
			'{{SignerCommonName}}' => $this->l10n->t('Common Name (CN) used to identify the document signer.'),
			'{{SignerEmail}}' => $this->l10n->t('The signer\'s email is optional and can be left blank.'),
			'{{SignerIdentifier}}' => $this->l10n->t('Unique information used to identify the signer (such as email, phone number, or username).'),
			'{{ValidationURL}}' => $this->l10n->t('Validation URL of the signed document.'),
			// TRANSLATORS This sentence is a description shown in the list of
			// available template variables.
			// Keep placeholder names unchanged: {{ qrcode }} and {{ValidationURL}}.
			// Keep this HTML snippet unchanged:
			// <img src="data:image/png;base64,{{ qrcode }}">
			'{{qrcode}}' => $this->l10n->t('Base64-encoded PNG QR code for the validation URL. In HTML/Twig, use <img src="data:image/png;base64,{{ qrcode }}">. In plain-text templates, use {{ValidationURL}}.'),
		];
		$collectMetadata = $this->isCollectMetadataEnabled();
		if ($collectMetadata) {
			$list['{{SignerIP}}'] = $this->l10n->t('IP address of the person who signed the document.');
			$list['{{SignerUserAgent}}'] = $this->l10n->t('Browser and device information of the person who signed the document.');
		}
		return $list;
	}

	private function getQrCodeImageBase64(string $text): string {
		$qrCode = new QrCode(
			data: $text,
			encoding: new Encoding('UTF-8'),
			errorCorrectionLevel: ErrorCorrectionLevel::Low,
			size: self::QRCODE_SIZE,
			margin: 4,
			roundBlockSizeMode: RoundBlockSizeMode::Margin,
			foregroundColor: new Color(0, 0, 0),
			backgroundColor: new Color(255, 255, 255)
		);

		$writer = new PngWriter();
		$result = $writer->write($qrCode);

		return base64_encode($result->getString());
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
		if (!extension_loaded('imagick')) {
			throw new Exception('Extension imagick is not loaded.');
		}
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
			$fallbackFond = __DIR__ . '/../../3rdparty/composer/mpdf/mpdf/ttfonts/DejaVuSerifCondensed.ttf';
			if (!file_exists($fallbackFond)) {
				$this->logger->error('No fonts available at system, and fallback font not found: ' . $fallbackFond);
				throw new LibresignException('No fonts available at system, and fallback font not found: ' . $fallbackFond);
			}
			$draw->setFont(__DIR__ . '/../../3rdparty/composer/mpdf/mpdf/ttfonts/DejaVuSerifCondensed.ttf');
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
		$wrappedText = $this->mbWordwrap($text, $maxCharsPerLine, "\n", true);

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
				$index >= 0
				&& $index < $length
				&& mb_substr($text, $index, 1) !== ' '
			) {
				$index += $direction;
			}

			if (
				$index > 0
				&& $index < $length
				&& mb_substr($text, $index, 1) === ' '
			) {
				$first = mb_substr($text, 0, $index);
				$second = mb_substr($text, $index + 1);
				$results[] = max(mb_strlen($first), mb_strlen($second));
			}
		}

		return !empty($results) ? max($results) : $length;
	}

	/**
	 * Multibyte-safe version of wordwrap
	 *
	 * @param string $text The text to wrap
	 * @param int $width The number of characters at which the string will be wrapped
	 * @param string $break The line break character
	 * @param bool $cut If true, words longer than $width will be broken
	 * @return string The wrapped text
	 */
	private function mbWordwrap(string $text, int $width, string $break = "\n", bool $cut = false): string {
		if ($width <= 0) {
			return $text;
		}

		$lines = [];
		$currentLine = '';
		$currentLength = 0;

		$paragraphs = explode("\n", $text);

		foreach ($paragraphs as $paragraphIndex => $paragraph) {
			if ($paragraph === '') {
				if ($currentLength > 0) {
					$lines[] = $currentLine;
					$currentLine = '';
					$currentLength = 0;
				}
				$lines[] = '';
				continue;
			}

			$words = explode(' ', $paragraph);

			foreach ($words as $word) {
				$wordLength = mb_strlen($word);

				if ($cut && $wordLength > $width) {
					if ($currentLength > 0) {
						$lines[] = $currentLine;
						$currentLine = '';
						$currentLength = 0;
					}

					while ($wordLength > $width) {
						$lines[] = mb_substr($word, 0, $width);
						$word = mb_substr($word, $width);
						$wordLength = mb_strlen($word);
					}

					if ($wordLength > 0) {
						$currentLine = $word;
						$currentLength = $wordLength;
					}
					continue;
				}

				$spaceLength = ($currentLength > 0) ? 1 : 0;
				if ($currentLength + $spaceLength + $wordLength > $width && $currentLength > 0) {
					$lines[] = $currentLine;
					$currentLine = $word;
					$currentLength = $wordLength;
				} else {
					if ($currentLength > 0) {
						$currentLine .= ' ';
						$currentLength++;
					}
					$currentLine .= $word;
					$currentLength += $wordLength;
				}
			}

			if ($currentLength > 0 && $paragraphIndex < count($paragraphs) - 1) {
				$lines[] = $currentLine;
				$currentLine = '';
				$currentLength = 0;
			}
		}

		if ($currentLength > 0) {
			$lines[] = $currentLine;
		}

		return implode($break, $lines);
	}

	public function getDefaultTemplate(): string {
		$collectMetadata = $this->isCollectMetadataEnabled();
		if ($collectMetadata) {
			// TRANSLATORS Variables enclosed in double curly braces {{variableName}} are template placeholders.
			//
			// DO NOT translate or remove these variables:
			// - {{SignerCommonName}}
			// - {{IssuerCommonName}}
			// - {{ServerSignatureDate}}
			// - {{SignerIP}}
			// - {{SignerUserAgent}}
			//
			// Only translate the text outside the curly braces, such as:
			// - "Signed with LibreSign"
			// - "Issuer:"
			// - "Date:"
			// - "IP:"
			// - "User agent:"
			return $this->l10n->t(
				"Signed with LibreSign\n"
				. "{{SignerCommonName}}\n"
				. "Issuer: {{IssuerCommonName}}\n"
				. "Date: {{ServerSignatureDate}}\n"
				. "IP: {{SignerIP}}\n"
				. 'User agent: {{SignerUserAgent}}'
			);
		}
		// TRANSLATORS Variables enclosed in double curly braces {{variableName}} are template placeholders.
		//
		// DO NOT translate or remove these variables:
		// - {{SignerCommonName}}
		// - {{IssuerCommonName}}
		// - {{ServerSignatureDate}}
		//
		// Only translate the text outside the curly braces, such as:
		// - "Signed with LibreSign"
		// - "Issuer:"
		// - "Date:"
		return $this->l10n->t(
			"Signed with LibreSign\n"
			. "{{SignerCommonName}}\n"
			. "Issuer: {{IssuerCommonName}}\n"
			. 'Date: {{ServerSignatureDate}}'
		);
	}

	public function getFullSignatureWidth(): float {
		return $this->getSanitizedDimension(SignatureTextPolicyProvider::KEY_SIGNATURE_WIDTH, self::DEFAULT_SIGNATURE_WIDTH);
	}

	public function getFullSignatureHeight(): float {
		return $this->getSanitizedDimension(SignatureTextPolicyProvider::KEY_SIGNATURE_HEIGHT, self::DEFAULT_SIGNATURE_HEIGHT);
	}

	public function getSignatureWidth(): float {
		$current = (float)$this->policyService->resolve(SignatureTextPolicyProvider::KEY_SIGNATURE_WIDTH)->getEffectiveValue();
		if ($this->getRenderMode() === SignerElementsService::RENDER_MODE_GRAPHIC_ONLY || !$this->getTemplate()) {
			return $current;
		}
		return $current / 2;
	}

	public function getSignatureHeight(): float {
		return $this->getFullSignatureHeight();
	}

	private function getSanitizedDimension(string $key, float $default): float {
		$value = (float)$this->policyService->resolve($key)->getEffectiveValue();
		if (!is_finite($value) || $value < self::SIGNATURE_DIMENSION_MINIMUM) {
			$this->logger->warning('Invalid signature dimension found in policy resolution. Falling back to default value in memory.', [
				'key' => $key,
				'value' => $value,
				'default' => $default,
			]);
			return $default;
		}
		return $value;
	}

	public function getTemplateFontSize(): float {
		return (float)$this->policyService->resolve(SignatureTextPolicyProvider::KEY_TEMPLATE_FONT_SIZE)->getEffectiveValue();
	}

	public function getDefaultTemplateFontSize(): float {
		$collectMetadata = $this->isCollectMetadataEnabled();
		if ($collectMetadata) {
			return self::TEMPLATE_DEFAULT_FONT_SIZE - 0.2;
		}
		return self::TEMPLATE_DEFAULT_FONT_SIZE;
	}

	private function isCollectMetadataEnabled(): bool {
		return (bool)$this->policyService->resolve(CollectMetadataPolicy::KEY)->getEffectiveValue();
	}

	public function getSignatureFontSize(): float {
		return (float)$this->policyService->resolve(SignatureTextPolicyProvider::KEY_SIGNATURE_FONT_SIZE)->getEffectiveValue();
	}

	public function getRenderMode(): string {
		return (string)$this->policyService->resolve(SignatureTextPolicyProvider::KEY_RENDER_MODE)->getEffectiveValue();
	}

	public function isEnabled(): bool {
		return !empty($this->getTemplate());
	}

	private function buildValidationUrl(string $uuid): string {
		$footerPolicy = FooterPolicyValue::normalize(
			$this->policyService->resolve(FooterPolicy::KEY)->getEffectiveValue()
		);
		$validationSite = trim($footerPolicy['validationSite']);
		if ($validationSite !== '') {
			return rtrim($validationSite, '/') . '/' . $uuid;
		}

		return $this->urlGenerator->linkToRouteAbsolute('libresign.page.validationFileWithShortUrl', [
			'uuid' => $uuid,
		]);
	}
}
