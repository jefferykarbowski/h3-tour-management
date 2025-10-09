#!/usr/bin/env bash
# Purpose: Idempotent environment setup and dependency installation
# Supports PHP/WordPress (Composer), Node (npm/yarn/pnpm) if present.

set -euo pipefail
IFS=$'\n\t'

project_root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")"/.. && pwd)"
cd "$project_root_dir"

# Helper: print to stderr
log() { printf '%s\n' "$*" >&2; }

# Ensure we can prepend local vendor binaries for this script's lifetime
prepend_path_once() {
  local dir="$1"
  if [ -d "$dir" ]; then
    case ":$PATH:" in
      *":$dir:"*) : ;; # already present
      *) export PATH="$dir:$PATH" ;;
    esac
  fi
}

# Detect manifests
has_composer=false
has_node=false

if [ -f "composer.json" ]; then
  has_composer=true
fi

if [ -f "package.json" ]; then
  has_node=true
fi

# Composer (PHP deps)
if [ "$has_composer" = true ]; then
  prepend_path_once "$project_root_dir/vendor/bin"
  if command -v composer >/dev/null 2>&1; then
    # Prefer install to respect lock; create vendor if missing.
    # Idempotent: safe to re-run.
    if [ -f "composer.lock" ]; then
      log "Installing PHP dependencies (composer install)…"
      composer install --no-interaction --prefer-dist --no-progress --ansi >/dev/null
    else
      log "composer.lock not found; running composer update to generate lock…"
      composer update --no-interaction --prefer-dist --no-progress --ansi >/dev/null
    fi
    prepend_path_once "$project_root_dir/vendor/bin"
  else
    log "Composer not found on PATH. Skipping PHP dependency install."
  fi
fi

# Node (JS tooling) — optional
if [ "$has_node" = true ]; then
  if command -v pnpm >/dev/null 2>&1; then
    if [ -f "pnpm-lock.yaml" ]; then
      log "Installing JS dependencies with pnpm (ci)…"
      pnpm i --frozen-lockfile >/dev/null
    else
      log "Installing JS dependencies with pnpm…"
      pnpm i >/dev/null
    fi
  elif command -v yarn >/dev/null 2>&1; then
    if [ -f "yarn.lock" ]; then
      log "Installing JS dependencies with yarn (frozen)…"
      yarn install --frozen-lockfile --silent >/dev/null
    else
      log "Installing JS dependencies with yarn…"
      yarn install --silent >/dev/null
    fi
  elif command -v npm >/dev/null 2>&1; then
    if [ -f "package-lock.json" ]; then
      log "Installing JS dependencies with npm ci…"
      npm ci --silent >/dev/null
    else
      log "Installing JS dependencies with npm…"
      npm install --silent >/dev/null
    fi
  else
    log "Node detected but no package manager found (npm/yarn/pnpm). Skipping JS install."
  fi
fi

# Surface how other scripts can use the environment
prepend_path_once "$project_root_dir/vendor/bin"
log "Environment ready. Local bin path: $project_root_dir/vendor/bin (if present)."

exit 0

