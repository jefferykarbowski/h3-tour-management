#!/usr/bin/env bash
# Purpose: Ensure environment, then run the project (best-effort based on manifests)

set -euo pipefail
IFS=$'\n\t'

project_root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")"/.. && pwd)"
cd "$project_root_dir"

# Silence install output as much as possible
if [ -x "tools/install.sh" ]; then
  bash tools/install.sh >/dev/null 2>&1 || {
    echo "Environment install failed" >&2
    exit 1
  }
fi

# Try Node start script if available
if [ -f "package.json" ] && command -v jq >/dev/null 2>&1; then
  if jq -e '.scripts.start' package.json >/dev/null 2>&1; then
    if command -v npm >/dev/null 2>&1; then
      npm run start --silent
      exit $?
    elif command -v yarn >/dev/null 2>&1; then
      yarn run start --silent
      exit $?
    elif command -v pnpm >/dev/null 2>&1; then
      pnpm run start
      exit $?
    fi
  fi
fi

# Try Composer start script
if [ -f "composer.json" ] && command -v composer >/dev/null 2>&1; then
  if php -r 'exit((int)!isset(json_decode(file_get_contents("composer.json"), true)["scripts"]["start"]));' ; then
    composer run --quiet start
    exit $?
  fi
fi

# PHP built-in server fallback if index.php exists (best effort)
if [ -f "index.php" ]; then
  echo "Launching PHP built-in server on 127.0.0.1:8000 (Ctrl+C to stop)…" >&2
  php -S 127.0.0.1:8000 >/dev/null 2>&1
  exit $?
fi

# WordPress plugin repositories typically have no direct runnable entrypoint.
echo "No runnable start target detected (WordPress plugin repository)." >&2
exit 0

