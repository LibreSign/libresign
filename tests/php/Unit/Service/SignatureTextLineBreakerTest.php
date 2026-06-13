<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Service;

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use PHPUnit\Framework\Attributes\DataProvider;

final class SignatureTextLineBreakerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private \OCA\Libresign\Service\SignatureTextLineBreaker $lineBreaker;

	#[\Override]
	public function setUp(): void {
		$this->lineBreaker = new \OCA\Libresign\Service\SignatureTextLineBreaker();
	}

	#[DataProvider('providerMaxCharsPerLine')]
	public function testGetMaxCharsPerLine(string $text, int $expected): void {
		$this->assertSame($expected, $this->lineBreaker->getMaxCharsPerLine($text));
	}

	public static function providerMaxCharsPerLine(): array {
		return [
			'empty string' => ['', mb_strlen('')],
			'single character' => ['A', mb_strlen('A')],
			'no spaces' => ['Loremipsumdolorsitamet', mb_strlen('Loremipsumdolorsitamet')],
			'space exactly in the middle' => ['Lorem ipsum', mb_strlen('Lorem')],
			'space after middle' => ['Open source rocks', mb_strlen('source rocks')],
			'space before middle' => ['Free software forever', mb_strlen('software forever')],
			'spaces on edges' => [' Leading and trailing ', mb_strlen('and trailing')],
			'unbalanced halves' => ['Short veryveryverylongword', mb_strlen('veryveryverylongword')],
			'equal halves' => ['One Two', mb_strlen('One')],
			'multiple words' => ['This is a longer string with more words', mb_strlen('This is a longer string')],
			'no possible split (no spaces)' => ['ABCDEFGHIJK', mb_strlen('ABCDEFGHIJK')],
			'only spaces' => ['     ', mb_strlen('')],
			'space at beginning' => [' HelloWorld', mb_strlen('HelloWorld')],
			'space at end' => ['HelloWorld ', mb_strlen('HelloWorld')],
			'two short words' => ['a b', mb_strlen('a')],
			'multibyte with accents' => ['João da Silva', mb_strlen('da Silva')],
			'multibyte at split' => ['José Antônio', mb_strlen('Antônio')],
			'multibyte no spaces' => ['Ñandúçara', mb_strlen('Ñandúçara')],
			'emoji in middle' => ['Smile 😀 always', mb_strlen('Smile 😀')],
			'emoji before space' => ['Good job 👍 team', mb_strlen('Good job 👍')],
			'emoji after space' => ['Team 👏 effort', mb_strlen('Team 👏')],
			'cjk characters with space' => ['漢字 漢字', mb_strlen('漢字')],
			'arabic with space' => ['سلام عليكم', mb_strlen('عليكم')],
			'emoji only' => ['😊 🙃', mb_strlen('😊')],
			'mixed accented and emoji' => ['Renée 💡 Dubois', mb_strlen('Renée 💡')],
			'greek with space' => ['Αθήνα Ελλάδα', mb_strlen('Ελλάδα')],
		];
	}

	#[DataProvider('providerWrapPreservesCharacters')]
	public function testWrapPreservesCharactersAcrossMultibyteScenarios(string $text): void {
		$wrappedText = $this->lineBreaker->wrap($text);

		$this->assertSame(
			$this->removeWhitespace($text),
			$this->removeWhitespace($wrappedText),
		);
	}

	public static function providerWrapPreservesCharacters(): array {
		return [
			'ascii words' => ['LibreSign digital signature service'],
			'long ascii word' => ['Loremipsumdolorsitametconsecteturadipiscingelit'],
			'portuguese accents' => ['Assinado digitalmente por João da Silva'],
			'french accents' => ['Signé numériquement par Renée Dubois'],
			'spanish accents and enye' => ['Firmado digitalmente por José Muñoz'],
			'greek' => ['Υπογραφή από Αθήνα'],
			'arabic' => ['توقيع رقمي من القاهرة'],
			'cyrillic' => ['Подписано цифровой подписью'],
			'chinese' => ['数字签名 北京'],
			'japanese' => ['デジタル署名 東京'],
			'korean' => ['디지털 서명 서울'],
			'emoji mixed with text' => ['Signed ✍️ by LibreSign 🔒'],
			'emoji and accents' => ['Signé 📝 par José 👤'],
			'preserves explicit line breaks' => ["João da Silva\nLibreSign"],
		];
	}

	private function removeWhitespace(string $text): string {
		return (string)preg_replace('/\s+/u', '', $text);
	}
}
