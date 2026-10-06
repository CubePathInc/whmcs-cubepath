#!/usr/bin/env bash
#
# Build a release archive that can be extracted directly over a WHMCS root.
#
# Usage: bin/build-release.sh <version>
# Output: dist/whmcs-cubepath-<version>.zip containing
#         modules/addons/cubepath/ (with vendor/) and modules/servers/cubepath/
#
set -euo pipefail

version="${1:?usage: $0 <version>}"
root="$(cd "$(dirname "$0")/.." && pwd)"
build="$root/dist/build"
archive="$root/dist/whmcs-cubepath-$version.zip"

rm -rf "$build" "$archive"
mkdir -p "$build/modules/addons" "$build/modules/servers"

cp -R "$root/addons/cubepath" "$build/modules/addons/cubepath"
cp -R "$root/servers/cubepath" "$build/modules/servers/cubepath"
rm -rf "$build/modules/addons/cubepath/vendor"

composer install \
    --working-dir="$build/modules/addons/cubepath" \
    --no-dev --no-interaction --no-progress --prefer-dist --classmap-authoritative

cp "$root/LICENSE" "$root/README.md" "$build/"

(cd "$build" && zip -qr -X "$archive" .)
rm -rf "$build"

echo "$archive"
