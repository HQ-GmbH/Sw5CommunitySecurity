#!/usr/bin/env bash
#
# Builds a release ZIP for the Shopware 5 plugin manager.
#
# The archive is created from a committed git revision (default: HEAD), so
# uncommitted changes are never shipped. Files marked "export-ignore" in
# .gitattributes are left out. The ZIP root is the folder
# "Sw5CommunitySecurity/", as required by the plugin manager.
#
# Usage: bin/build-zip.sh [git-revision]

set -euo pipefail

readonly PLUGIN_NAME="Sw5CommunitySecurity"

cd "$(dirname "$0")/.."

revision="${1:-HEAD}"

version="$(git show "${revision}:plugin.xml" | sed -n 's:.*<version>\(.*\)</version>.*:\1:p' | head -n 1)"
if [[ -z "${version}" ]]; then
    echo "Could not read <version> from plugin.xml at ${revision}." >&2
    exit 1
fi

if [[ "${revision}" == "HEAD" ]] && [[ -n "$(git status --porcelain)" ]]; then
    echo "Warning: the working tree has uncommitted changes; they are NOT part of the ZIP." >&2
fi

mkdir -p build
output="build/${PLUGIN_NAME}-${version}.zip"

git archive --format=zip --prefix="${PLUGIN_NAME}/" --output="${output}" "${revision}"

echo "${output}"
