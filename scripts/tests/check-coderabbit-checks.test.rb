# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

require 'minitest/autorun'
require_relative '../check-coderabbit-checks'

class CoderabbitChecksTest < Minitest::Test
  ROOT = File.expand_path('../..', __dir__)
  FILE = 'sample.md'
  TRACKED = %w[lib/Service/Example.php 3rdparty README.md].freeze
  VALID = <<~MARKDOWN
    ---
    id: sample
    name: Sample check
    mode: warning
    report: coderabbit
    paths:
      - "lib/**"
      - "3rdparty"
    ---

    Check the affected invariant.
  MARKDOWN

  def test_valid_document
    assert_equal 'sample', CoderabbitChecks.parse(VALID, FILE, TRACKED)['id']
  end

  def test_invalid_documents
    cases = {
      duplicate_key: VALID.sub('id: sample', "id: sample\nid: sample"),
      malformed_yaml: VALID.sub('name: Sample check', 'name: [invalid'),
      unknown_field: VALID.sub('mode: warning', "mode: warning\nunknown: true"),
      missing_field: VALID.sub("report: coderabbit\n", ''),
      mismatched_id: VALID.sub('id: sample', 'id: another'),
      blocking_mode: VALID.sub('mode: warning', 'mode: error'),
      platform_report: VALID.sub('report: coderabbit', 'report: github'),
      missing_front_matter: VALID.sub('---', 'not a delimiter'),
      long_name: VALID.sub('Sample check', 'x' * 51),
      comment_only: VALID.sub('Check the affected invariant.', '<!-- not an instruction -->'),
      empty_body: VALID.sub('Check the affected invariant.', ''),
      long_body: VALID.sub('Check the affected invariant.', 'x' * 10_001),
      duplicate_path: VALID.sub('"3rdparty"', '"lib/**"'),
      absolute_path: VALID.sub('"3rdparty"', '"/lib/**"'),
      parent_path: VALID.sub('"3rdparty"', '"lib/../README.md"'),
      unsupported_glob: VALID.sub('"3rdparty"', '"**/*.php"'),
      dead_path: VALID.sub('"3rdparty"', '"missing/**"')
    }
    cases.each do |name, document|
      assert_raises(ArgumentError, name.to_s) { CoderabbitChecks.parse(document, FILE, TRACKED) }
    end
  end

  def test_duplicate_ids
    assert_raises(ArgumentError) do
      CoderabbitChecks.validate({ FILE => VALID, 'other/sample.md' => VALID }, TRACKED)
    end
  end

  def test_paths_are_anchored_and_recursive
    assert CoderabbitChecks.matches?('lib/**', 'lib/Service/Example.php')
    assert CoderabbitChecks.matches?('lib/**', 'lib/Example.php')
    refute CoderabbitChecks.matches?('lib/**', 'other/lib/Example.php')
    refute CoderabbitChecks.matches?('lib/**', 'library/Example.php')
    assert CoderabbitChecks.matches?('3rdparty', '3rdparty')
    refute CoderabbitChecks.matches?('3rdparty', '3rdparty/README.md')
  end

  def test_real_check_coverage
    required_paths = {
      'regression-ci-evidence' => %w[behat.php vitest.config.js playwright.config.ts infection.json5 stryker.config.mjs eslint.config.mjs stylelint.config.js tsconfig.json rector.php .php-cs-fixer.dist.php .octocov.yml .devcontainer/docker-compose.yml .nextcloud-release.yml vite.config.mjs composer.lock package-lock.json 3rdparty .github/actions-lock.txt .github/dependabot.yml .github/renovate.json5 REUSE.toml],
      'authorization-signature-integrity' => %w[3rdparty .gitmodules templates/reset_password.php tests/php/Integration/Handler/SignEngine/PhpNativeSigningRegressionTest.php tests/integration/features/policies/docmdp_policy.feature playwright/e2e/direct-sign-api.spec.ts composer.lock],
      'contract-upgrade-safety' => %w[3rdparty composer/autoload.php src/services/FilesService.ts src/router/router.ts vendor-bin/phpunit/composer.lock .nextcloud-release.yml redocly.yaml Makefile openapi.json openapi-full.json openapi-administration.json src/types/openapi/openapi.ts]
    }
    tracked, error, status = Open3.capture3('git', '-C', ROOT, 'ls-files', '-z')
    assert status.success?, error
    tracked = tracked.split("\0")
    required_paths.each do |id, paths|
      path = File.join(ROOT, CoderabbitChecks::DIRECTORY, "#{id}.md")
      data = CoderabbitChecks.parse(File.read(path), path, tracked)
      paths.each do |candidate|
        assert_includes tracked, candidate
        assert data['paths'].any? { |pattern| CoderabbitChecks.matches?(pattern, candidate) }, "#{id} misses #{candidate}"
      end
      refute data['paths'].any? { |pattern| CoderabbitChecks.matches?(pattern, 'README.md') }, "#{id} matches unrelated docs"
    end
  end
end
