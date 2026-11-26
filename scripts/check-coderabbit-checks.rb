# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

require 'psych'
require 'open3'

module CoderabbitChecks
  DIRECTORY = '.coderabbit/pre-merge-checks'
  FIELDS = %w[id name mode report paths].freeze

  # Intentionally support only literal repository-relative paths and recursive
  # directory prefixes (/**), the subset used by these advisory checks.
  def self.valid_pattern?(pattern)
    return false unless pattern.is_a?(String)

    pattern.match?(%r{\A(?:[\w.-]+/)*[\w.-]+(?:/\*\*)?\z}) &&
      (pattern.split('/') & %w[. ..]).empty?
  end

  def self.matches?(pattern, path)
    pattern.end_with?('/**') ? path.start_with?(pattern.delete_suffix('**')) : path == pattern
  end

  def self.parse(text, filename, tracked_paths)
    lines = text.lines
    closing = lines.each_index.find { |i| i.positive? && lines[i].chomp == '---' }
    raise ArgumentError, 'missing YAML front matter' unless lines.first&.chomp == '---' && closing

    header = lines[1...closing].join
    documents = Psych.parse_stream(header).children
    root = documents.first&.root
    unless documents.size == 1 && root.is_a?(Psych::Nodes::Mapping)
      raise ArgumentError, 'expected a single YAML mapping'
    end

    keys = root.children.each_slice(2).map do |key, _value|
      raise ArgumentError, 'expected scalar field names' unless key.is_a?(Psych::Nodes::Scalar)

      key.value
    end
    raise ArgumentError, 'duplicate YAML field' unless keys.uniq == keys
    raise ArgumentError, 'unexpected or missing fields' unless keys.sort == FIELDS.sort

    data = Psych.safe_load(header, aliases: false)
    id = data['id']
    unless id.is_a?(String) && id.match?(/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/) && id.length <= 50 &&
           File.basename(filename, '.md') == id
      raise ArgumentError, 'invalid id or filename does not match id'
    end
    name = data['name']
    unless name.is_a?(String) && !name.strip.empty? && name.length <= 50
      raise ArgumentError, 'name must contain 1-50 characters'
    end
    unless data['mode'] == 'warning' && data['report'] == 'coderabbit'
      raise ArgumentError, 'checks must remain advisory in the CodeRabbit walkthrough'
    end
    body = lines[(closing + 1)..].join.strip
    raise ArgumentError, 'body must contain 1-10000 characters' if body.gsub(/<!--.*?-->/m, '').strip.empty? || body.length > 10_000

    paths = data['paths']
    unless paths.is_a?(Array) && !paths.empty? && paths.uniq == paths && paths.all? { |p| valid_pattern?(p) }
      raise ArgumentError, 'paths must be unique literal paths or recursive directory prefixes'
    end
    paths.each do |pattern|
      raise ArgumentError, "path matches no tracked entry: #{pattern}" unless tracked_paths.any? { |p| matches?(pattern, p) }
    end
    data
  rescue Psych::Exception => e
    raise ArgumentError, "invalid YAML: #{e.message}"
  end

  def self.validate(documents, tracked_paths)
    ids = []
    documents.each do |filename, content|
      data = parse(content, filename, tracked_paths)
      raise ArgumentError, 'duplicate id' if ids.include?(data['id'])

      ids << data['id']
    rescue ArgumentError => e
      raise ArgumentError, "#{filename}: #{e.message}"
    end
    raise ArgumentError, 'no pre-merge check documents' if ids.empty?

    ids
  end
end

if $PROGRAM_NAME == __FILE__
  root = File.expand_path('..', __dir__)
  output, error, status = Open3.capture3('git', '-C', root, 'ls-files', '-z')
  abort error unless status.success?

  paths = output.split("\0")
  documents = paths.grep(%r{\A#{Regexp.escape(CoderabbitChecks::DIRECTORY)}/[^/]+\.md\z}).to_h do |path|
    [path, File.read(File.join(root, path))]
  end
  begin
    ids = CoderabbitChecks.validate(documents, paths)
    puts "Validated #{ids.size} advisory CodeRabbit checks."
  rescue ArgumentError => e
    abort e.message
  end
end
