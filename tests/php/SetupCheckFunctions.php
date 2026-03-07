<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\SetupCheck;

if (!function_exists('OCA\Libresign\SetupCheck\file_exists')) {
  function file_exists(string $filename): bool
  {
    if (array_key_exists($filename, FileSystemMock::$files)) {
      return FileSystemMock::$files[$filename];
    }
    return \file_exists($filename);
  }
}

if (!function_exists('OCA\Libresign\SetupCheck\exec')) {
  function exec(string $command, &$output = null, &$result_code = null): string|false
  {
    if (array_key_exists($command, ExecMock::$commands)) {
      $mock = ExecMock::$commands[$command];
      $output = $mock['output'];
      $result_code = $mock['result_code'];
      return $output ? implode("\n", $output) : '';
    }
    return \exec($command, $output, $result_code);
  }
}
