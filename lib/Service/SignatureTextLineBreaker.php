<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

class SignatureTextLineBreaker {
	public function getMaxCharsPerLine(string $text): int {
		$text = trim($text);
		$length = mb_strlen($text);

		if ($length === 0) {
			return 0;
		}

		$middle = (int)($length / 2);
		$results = [];

		foreach (['backward' => -1, 'forward' => 1] as $direction) {
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

	public function wrap(string $text): string {
		return $this->mbWordwrap($text, $this->getMaxCharsPerLine($text), "\n", true);
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
}
