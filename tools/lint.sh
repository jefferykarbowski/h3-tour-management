#!/usr/bin/env bash
# Purpose: Ensure environment then lint source code.
# - For this PHP/WordPress project, run PHP syntax checks.
# - Output MUST be valid JSON ONLY to stdout.
# - Suppress other outputs; send operational logs to stderr if needed.

set -euo pipefail
IFS=$'\n\t'

project_root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")"/.. && pwd)"
cd "$project_root_dir"

# Run install silently to avoid polluting JSON output
if [ -x "tools/install.sh" ]; then
  bash tools/install.sh >/dev/null 2>&1 || {
    # On failure, emit a JSON error and exit non-zero
    printf '{"type":"tool_error","path":"","obj":"install","message":"install failed","line":"","column":""}\n'
    exit 2
  }
fi

# Collect PHP files
mapfile -t php_files < <(find . -type f \( -name "*.php" -o -name "*.phtml" \) \
  -not -path "*/vendor/*" -not -path "*/node_modules/*" | sort)

errors_json="[]"

if [ ${#php_files[@]} -gt 0 ]; then
  # We cannot let set -e abort on first php -l error; capture per-file
  for f in "${php_files[@]}"; do
    # Run lint; php -l outputs e.g., "Errors parsing file.php" or "No syntax errors detected"
    out="$(php -l "$f" 2>&1 || true)"
    if printf '%s' "$out" | grep -qiE '^no syntax errors detected'; then
      continue
    fi
    if printf '%s' "$out" | grep -qiE 'in \s*.+ on line \s*[0-9]+'; then
      # Try to parse: "Parse error: syntax error, unexpected T_STRING in path on line 12"
      msg="$out"
      line=""
      if [[ "$out" =~ on\ line\ ([0-9]+) ]]; then
        line="${BASH_REMATCH[1]}"
      fi
      # Column is not provided by php -l; leave empty string per required schema
      # Build JSON object and append to array
      # Escape quotes in message
      safe_msg=$(printf '%s' "$msg" | sed 's/\\/\\\\/g; s/"/\\"/g')
      safe_path=$(printf '%s' "$f" | sed 's/\\/\\\\/g; s/"/\\"/g')
      new_item=$(printf '{"type":"syntax_error","path":"%s","obj":"","message":"%s","line":"%s","column":""}' \
        "$safe_path" "$safe_msg" "$line")
      # Append to JSON array (simple, since items are small)
      if [ "$errors_json" = "[]" ]; then
        errors_json="[$new_item]"
      else
        errors_json="${errors_json%]} , $new_item]"
      fi
    fi
  done
fi

# Print JSON ONLY to stdout
printf '%s\n' "$errors_json"

# Exit code: 0 if no errors, non-zero otherwise
if [ "$errors_json" != "[]" ]; then
  exit 1
fi
exit 0

