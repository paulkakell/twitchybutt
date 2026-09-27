#!/usr/bin/env bash
# Explicit test-runner provisioning, not a production installer.
set -euo pipefail
if [ "${GITHUB_ACTIONS:-}" != true ]; then
  printf '%s\n' 'This script only provisions an ephemeral GitHub Actions runner.' >&2
  exit 1
fi
sudo apt-get update -qq
sudo apt-get install -y --no-install-recommends php8.3-gd ffmpeg
php -r 'if (!extension_loaded("gd") || !extension_loaded("pcntl")) { fwrite(STDERR, "Media test prerequisites missing.\n"); exit(1); }'
command -v ffmpeg
command -v ffprobe
mkdir -p build
ffmpeg -version > build/media-tools.txt
ffprobe -version >> build/media-tools.txt
php -r 'echo "GD: ".json_encode(gd_info()).PHP_EOL;' >> build/media-tools.txt
dpkg-query -W -f='${Package}\t${Version}\n' php8.3-gd ffmpeg > build/media-packages.txt
