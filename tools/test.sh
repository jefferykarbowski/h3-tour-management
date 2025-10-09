#!/usr/bin/env bash
# Purpose: Ensure environment then run tests (if any). Fallback to PHP lint.

set -euo pipefail
IFS=$'\n\t'

project_root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")"/.. && pwd)"
cd "$project_root_dir"

# Ensure env silently
if [ -x "tools/install.sh" ]; then
  bash tools/install.sh >/dev/null 2>&1 || {
    echo "Environment install failed" >&2
    exit 1
  }
fi

# Prefer PHPUnit if present
if [ -x "vendor/bin/phpunit" ]; then
  vendor/bin/phpunit --colors=never --do-not-cache-result --debug=0 >/dev/null
  exit $?
fi

# Prefer Node tests if present
if [ -f "package.json" ]; then
  if command -v jq >/dev/null 2>&1 && jq -e '.scripts.test' package.json >/dev/null 2>&1; then
    if command -v npm >/dev/null 2>&1; then
      npm test --silent
      exit $?
    elif command -v yarn >/dev/null 2>&1; then
      yarn test --silent
      exit $?
    elif command -v pnpm >/dev/null 2>&1; then
      pnpm test
      exit $?
    fi
  fi
fi

# Fallback: PHP syntax check as a smoke test
mapfile -t php_files < <(find . -type f \( -name "*.php" -o -name "*.phtml" \) \
  -not -path "*/vendor/*" -not -path "*/node_modules/*")

if [ ${#php_files[@]} -eq 0 ]; then
  echo "No tests or PHP files detected; nothing to do." >&2
  exit 0
fi

had_err=0
for f in "${php_files[@]}"; do
  if ! php -l "$f" >/dev/null 2>&1; then
    echo "Syntax error in: $f" >&2
    had_err=1
  fi
done

exit $had_err

