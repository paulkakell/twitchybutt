# frozen_string_literal: true
# Standard-library YAML/structural checks; not a replacement for GitHub activation checks.
require 'yaml'

ROOT = File.expand_path('..', __dir__)
CHECKS = []

def check(condition, message)
  raise message unless condition
  CHECKS << message
end

def reject_duplicate_keys(node, path)
  if node.is_a?(Psych::Nodes::Mapping)
    keys = node.children.each_slice(2).map { |key, _| key.value }
    check(keys.uniq.length == keys.length, "#{path}: duplicate YAML keys")
  end
  (node.children || []).each { |child| reject_duplicate_keys(child, path) }
end

def load_yaml(path)
  text = File.read(File.join(ROOT, path))
  reject_duplicate_keys(Psych.parse_stream(text), path)
  value = YAML.safe_load(text, permitted_classes: [], permitted_symbols: [], aliases: false, filename: path)
  check(value.is_a?(Hash), "#{path}: expected a mapping")
  value
end

def validate_form(form, path, issue:)
  if issue
    check(form['name'].is_a?(String) && !form['name'].empty?, "#{path}: issue name")
    check(form['description'].is_a?(String) && !form['description'].empty?, "#{path}: description")
  end
  body = form['body']
  check(body.is_a?(Array) && !body.empty?, "#{path}: nonempty body")
  inputs = body.reject { |field| field['type'] == 'markdown' }
  check(!inputs.empty?, "#{path}: at least one input")
  ids = inputs.map { |field| field['id'] }
  check(ids.all? { |id| id.is_a?(String) && id.match?(/\A[a-zA-Z0-9_-]+\z/) }, "#{path}: valid field IDs")
  check(ids.uniq.length == ids.length, "#{path}: unique field IDs")
  body.each do |field|
    check(%w[markdown input textarea dropdown checkboxes].include?(field['type']), "#{path}: supported field type")
    attrs = field['attributes']
    check(attrs.is_a?(Hash), "#{path}: field attributes")
    key = field['type'] == 'markdown' ? 'value' : 'label'
    check(attrs[key].is_a?(String) && !attrs[key].empty?, "#{path}: field #{key}")
  end
end

begin
  paths = Dir.chdir(ROOT) { Dir.glob('.github/**/*.{yml,yaml}').sort }
  documents = paths.to_h { |path| [path, load_yaml(path)] }
  documents.each do |path, doc|
    if path.start_with?('.github/ISSUE_TEMPLATE/') && !path.end_with?('/config.yml')
      validate_form(doc, path, issue: true)
    elsif path.start_with?('.github/DISCUSSION_TEMPLATE/')
      validate_form(doc, path, issue: false)
    end
  end
  %w[bug-report.yml documentation.yml feature-request.yml].each do |name|
    check(documents.key?(".github/ISSUE_TEMPLATE/#{name}"), "Required issue form: #{name}")
  end
  chooser = documents.fetch('.github/ISSUE_TEMPLATE/config.yml')
  check(chooser['blank_issues_enabled'] == false, 'Blank issue intake disabled')
  contacts = chooser.fetch('contact_links')
  check(contacts.is_a?(Array) && !contacts.empty?, 'Contact links must be a nonempty array')
  check(contacts.all? { |link| link.is_a?(Hash) && link['url'].is_a?(String) }, 'Contact URLs must be strings')
  links = contacts.map { |link| link.fetch('url') }
  # Compare each complete URL explicitly. Never accept a trusted URL embedded in
  # an attacker-controlled host, path, query, fragment or userinfo component.
  policy_url = 'https://github.com/paulkakell/twitchybutt/security/policy'
  support_url = 'https://github.com/paulkakell/twitchybutt/discussions'
  check(links.any? { |url| url == policy_url }, 'Private-reporting policy linked')
  check(links.any? { |url| url == support_url }, 'Community support linked')
  check(links.all? { |url| url == policy_url || url == support_url }, 'Only canonical owner contact destinations')
  check(links.uniq.length == links.length, 'Contact destinations must not be duplicated')
  check(documents.fetch('.github/FUNDING.yml') == { 'github' => ['paulkakell'] }, 'Only owner funding destination')

  dependabot = documents.fetch('.github/dependabot.yml')
  check(dependabot['version'] == 2, 'Dependabot schema version')
  updates = dependabot.fetch('updates')
  check(updates.map { |entry| entry['package-ecosystem'] }.sort == %w[composer github-actions], 'Actual supported ecosystems')
  updates.each do |entry|
    ecosystem = entry.fetch('package-ecosystem')
    check(entry['directory'] == '/', "#{ecosystem}: root directory")
    check(entry['schedule']['interval'] == 'weekly' && entry['schedule']['day'] == 'monday', "#{ecosystem}: weekly Monday schedule")
    check(entry['schedule']['timezone'] == 'America/Denver', "#{ecosystem}: explicit timezone")
    check(entry['open-pull-requests-limit'].is_a?(Integer) && entry['open-pull-requests-limit'].positive?, "#{ecosystem}: enabled version updates")
    check(!entry.key?('ignore') && !entry.key?('target-branch'), "#{ecosystem}: no ignored fixes or redirected security updates")
    check(entry['assignees'] == ['paulkakell'], "#{ecosystem}: owner assignment")
    groups = entry.fetch('groups').values
    check(groups.any? { |group| group['applies-to'] == 'security-updates' && group['patterns'] == ['*'] }, "#{ecosystem}: security update grouping")
    routine = groups.select { |group| group['applies-to'] == 'version-updates' }
    check(!routine.empty? && routine.all? { |group| group['update-types'].sort == %w[minor patch] }, "#{ecosystem}: majors stay individual")
  end

  documents.select { |path, _| path.start_with?('.github/workflows/') }.each do |path, workflow|
    events = workflow.fetch('on') { workflow.fetch(true) }
    check(!events.key?('pull_request_target'), "#{path}: no privileged PR-target execution")
    check(workflow.fetch('permissions') == { 'contents' => 'read' }, "#{path}: least-privilege default")
    workflow.fetch('jobs').each do |name, job|
      permissions = job.fetch('permissions', {})
      if permissions.values.include?('write')
        check(path == '.github/workflows/repository-setup.yml' && name == 'apply', "#{path}: scoped community writes only")
        check(permissions == { 'contents' => 'read', 'discussions' => 'write', 'issues' => 'write' }, 'No code/admin write permission')
        check(job.fetch('if').include?("github.ref == 'refs/heads/main'") && job['if'].include?("github.repository == 'paulkakell/twitchybutt'"), 'Write job restricted to expected main')
      end
      job.fetch('steps', []).each do |step|
        next unless step.key?('uses')
        check(step['uses'].match?(/\A[\w.-]+\/[\w.\/-]+@[0-9a-f]{40}\z/), "#{path}: full action SHA pin")
        if step['uses'].start_with?('actions/checkout@')
          check(step.fetch('with')['persist-credentials'] == false, "#{path}: checkout credentials not persisted")
        end
      end
    end
  end
  puts "Repository configuration passed: #{paths.length} YAML files; #{CHECKS.length} structural/policy checks."
rescue StandardError => error
  warn "Repository configuration failed: #{error.message}"
  exit 1
end
