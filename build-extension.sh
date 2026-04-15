#!/usr/bin/env bash
# Packages the SMS Message EspoCRM extension into a ZIP archive.
# Output: build/sms-message-<version>.zip

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VERSION=$(python3 - <<'PY' "${SCRIPT_DIR}/manifest.json"
import json
import re
import sys
from pathlib import Path

manifest_path = Path(sys.argv[1])
data = json.loads(manifest_path.read_text())
current = str(data.get("version", "0"))

match = re.fullmatch(r"(\d+)(?:\.(\d+))?(?:\.(\d+))?", current)

if not match:
    raise SystemExit(f"Unsupported manifest version format: {current}")

parts = [p for p in match.groups() if p is not None]
parts[-1] = str(int(parts[-1]) + 1)
next_version = ".".join(parts)

data["version"] = next_version
manifest_path.write_text(json.dumps(data, indent=4) + "\n")

print(next_version)
PY
)
PACKAGE_NAME="sms-message-${VERSION}"
BUILD_DIR="${SCRIPT_DIR}/build"
STAGE_DIR="${BUILD_DIR}/${PACKAGE_NAME}"
OUT_FILE="${BUILD_DIR}/${PACKAGE_NAME}.zip"

echo "Building extension: ${PACKAGE_NAME}"

# Clean previous staging directory
rm -rf "${STAGE_DIR}"
mkdir -p "${STAGE_DIR}"

# Copy manifest
cp "${SCRIPT_DIR}/manifest.json" "${STAGE_DIR}/manifest.json"

# Copy backend custom files
mkdir -p "${STAGE_DIR}/files/custom/Espo/Custom"
rsync -a --exclude='.htaccess' \
    "${SCRIPT_DIR}/custom/Espo/Custom/" \
    "${STAGE_DIR}/files/custom/Espo/Custom/"

# Copy frontend custom files (src and res, skip gitignore/dummy files)
mkdir -p "${STAGE_DIR}/files/client/custom"
rsync -a \
    --exclude='.gitignore' \
    --exclude='dummy.txt' \
    --exclude='modules/' \
    "${SCRIPT_DIR}/client/custom/" \
    "${STAGE_DIR}/files/client/custom/"

# Create ZIP with files at archive root (not under a subdirectory)
rm -f "${OUT_FILE}"
cd "${STAGE_DIR}"
zip -r "${OUT_FILE}" .
cd "${SCRIPT_DIR}"
rm -rf "${STAGE_DIR}"

echo "Created: ${OUT_FILE}"
