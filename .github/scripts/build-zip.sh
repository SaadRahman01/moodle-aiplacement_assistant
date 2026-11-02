#!/bin/bash
# Build the Moodle Marketplace ZIP from a committed revision (default HEAD).
# Usage: .github/scripts/build-zip.sh [revision]
# Files marked export-ignore in .gitattributes are left out.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
rev="${1:-HEAD}"
release=$(git show "$rev:version.php" | sed -n "s/^\$plugin->release = '\(.*\)';/\1/p")
out="aiplacement_assistant_${release}.zip"
git archive --format=zip --prefix=assistant/ -o "$out" "$rev"
echo "$out"
