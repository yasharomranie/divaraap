#!/usr/bin/env bash
set -euo pipefail
project_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
tools_root="${GRAPHIFY_TOOLS_ROOT:-$(dirname -- "$project_root")}"
export UV_CACHE_DIR="$tools_root/.cache/uv"
export UV_TOOL_DIR="$tools_root/.tools/uv"
export UV_TOOL_BIN_DIR="$tools_root/bin"
export XDG_CACHE_HOME="$tools_root/.cache"
export PATH="$UV_TOOL_BIN_DIR:$PATH"
if [[ ! -x "$UV_TOOL_BIN_DIR/graphify" ]]; then
    uv tool install graphifyy==0.9.90
fi
cd -- "$project_root"
exec "$UV_TOOL_BIN_DIR/graphify" "$@"
