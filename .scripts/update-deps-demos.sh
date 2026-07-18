#!/usr/bin/env bash
# Update Composer dependencies inside every Symfony demo of a bundle.
# Usage: update-deps-demos.sh /absolute/path/to/BundleName

set -euo pipefail

BUNDLE_ROOT="${1:?Bundle root path required}"
DEMO_DIR="${BUNDLE_ROOT}/demo"

if [[ ! -d "${DEMO_DIR}" ]]; then
  exit 0
fi

demo_port() {
  local demo_path="$1"
  local port=""

  if [[ -f "${demo_path}/.env" ]]; then
    port="$(grep '^PORT=' "${demo_path}/.env" 2>/dev/null | cut -d= -f2 | tr -d '\r' || true)"
  fi
  if [[ -z "${port}" ]] && [[ -f "${demo_path}/.env.example" ]]; then
    port="$(grep '^PORT=' "${demo_path}/.env.example" 2>/dev/null | cut -d= -f2 | tr -d '\r' || true)"
  fi
  printf '%s' "${port}"
}

start_demo_after_update() {
  local demo_path="$1"
  local demo_name
  demo_name="$(basename "${demo_path}")"
  local port
  port="$(demo_port "${demo_path}")"

  echo "=== Demo ${demo_name}: starting demo ==="
  (
    cd "${demo_path}"
    if [[ -n "${port}" ]]; then
      PORT="${port}" docker compose up -d
    else
      docker compose up -d
    fi
    echo "Waiting for container to be ready..."
    sleep 5
    docker compose exec -T php php bin/console importmap:install --no-interaction 2>/dev/null || true
    docker compose exec -T php php bin/console cache:clear --no-interaction 2>/dev/null || true
    docker compose exec -T php php bin/console assets:install public --symlink --no-interaction 2>/dev/null || true
    if [[ -n "${port}" ]]; then
      echo "Demo started at: http://localhost:${port}"
    fi
  )
}

run_demo_update() {
  local demo_path="$1"
  local demo_name
  demo_name="$(basename "${demo_path}")"

  echo ""
  echo "=== Demo ${demo_name}: composer update (one-off container) ==="
  (
    cd "${demo_path}"
    if [[ ! -f .env ]] && [[ -f .env.example ]]; then
      cp .env.example .env
    fi

    if [[ -f Makefile ]] && make -n update-deps >/dev/null 2>&1; then
      make update-deps
      exit 0
    fi

    compose_cmd=(docker compose)
    if [[ -f docker-compose.yml ]] || [[ -f docker-compose.yaml ]]; then
      "${compose_cmd[@]}" run --rm --no-deps \
        -e COMPOSER_MEMORY_LIMIT=-1 \
        --entrypoint "" \
        php composer update --no-interaction --no-scripts
      start_demo_after_update "${demo_path}"
    fi
  )
}

if [[ -f "${DEMO_DIR}/Makefile" ]] && make -C "${DEMO_DIR}" -n update-deps-all >/dev/null 2>&1; then
  make -C "${DEMO_DIR}" update-deps-all
  exit 0
fi

shopt -s nullglob
for demo_path in "${DEMO_DIR}"/*/; do
  [[ -d "${demo_path}" ]] || continue
  if [[ -f "${demo_path}/docker-compose.yml" ]] || [[ -f "${demo_path}/docker-compose.yaml" ]]; then
    run_demo_update "${demo_path}"
  fi
done

echo ""
echo "✅ Demo dependency updates completed for $(basename "${BUNDLE_ROOT}")"
