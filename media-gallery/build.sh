#!/bin/bash
# build.sh - the whole static gallery in one command.
#   ~/gallery3-replacement/build.sh            (source: ~/gallery3-originals/albums, from gallery3-copy-off-originals.sh)
#   SRC=... OUT=... TITLE=... ./build.sh       to change a default;   CLEAN=1 ./build.sh   starts from an empty staging folder
# Output: ~/gallery3-replacement/staging  (open staging/index.html in a browser to look before publishing it with
# TO_BE_RUN/gallery3-publish-static-gallery.sh).  The originals are never changed.
set -euo pipefail
here=$(cd "$(dirname "$0")" && pwd)
SRC=${SRC:-$HOME/gallery3-originals/albums}
OUT=${OUT:-$here/staging}
TITLE=${TITLE:-Universal Laughter media}
[[ -d $SRC ]] || { echo "no originals at $SRC: run TO_BE_RUN/gallery3-copy-off-originals.sh first"; exit 1; }
args=(--src "$SRC" --out "$OUT" --title "$TITLE")
[[ ${CLEAN:-0} == 1 ]] && args+=(--clean)
python3 "$here/build-gallery.py" "${args[@]}"
echo "size: $(du -sh "$OUT" | cut -f1), files: $(ls -R "$OUT" | grep -c -E '\.[A-Za-z0-9]+$')"
