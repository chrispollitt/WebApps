# media-gallery

Static photo gallery generator behind https://media.universal-laughter.com/ (replaced Gallery 3, which broke on PHP 8.3).
No database, no PHP: it turns a folder of originals into plain HTML, resized pictures and thumbnails, and a lightbox.

    ./build.sh          # source ~/gallery3-originals/albums  ->  output ~/gallery3-replacement/staging
    SRC=... OUT=... TITLE=... ./build.sh      # override the defaults;  CLEAN=1 starts from an empty output folder

- Needs Python 3 and Pillow; ffmpeg/ffprobe for videos. Pictures are rotated by their EXIF orientation, then all metadata
  (GPS included) is dropped; videos in h264+aac mp4 are copied unchanged, anything else is converted once.
- The originals are never changed and are not published.
- Originals come off the host with `gallery3-copy-off-originals.sh`; the staged site is published with
  `gallery3-publish-static-gallery.sh` (both in `/home/chris/src/Claude/TO_BE_RUN/DONE` after their runs). The publish script
  uploads with permissions D755,F644 (files must be world-readable for the web server) and keeps a backup of `.htaccess`.
- The old Gallery 3 tree and its database are still on the host (`~/www/ul/ul-media`), untouched; remove them only when the owner says so.
- Source: TUIs issue 60 (chrispollitt/TUIs#60).
