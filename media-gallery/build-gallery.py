#!/usr/bin/env python3
"""build-gallery.py - a static photo gallery from a folder of originals.  No database, no PHP, nothing to patch.

    python3 build-gallery.py --src ~/gallery3-originals/albums --out ~/gallery3-replacement/staging --title "Universal Laughter media"

SRC is a folder of folders: each folder is an album, folders inside it are sub-albums, pictures (.jpg .jpeg .png .gif) and
videos (.mp4 .m4v .webm .mov) are the items.  OUT gets, for every album, an index.html (sub-albums with a cover picture, then
the items), plus resized pictures, thumbnails and a lightbox (gallery.js: click a picture, arrow keys / Esc / swipe-free).

  - needs Python 3 and Pillow; ffmpeg/ffprobe for videos (poster frame; a transcode only if the browser cannot play the file)
  - pictures: rotated by their EXIF orientation, then ALL metadata dropped (no GPS in the published copies); display 1600 px
    on the long side, thumbnail 360 px; the originals are NOT published (--originals copies them too)
  - videos: h264 + aac in mp4 are copied as they are, anything else is converted once with ffmpeg
  - re-runnable: an output newer than its source is left alone; --clean first removes OUT's generated files
  - names are HTML-escaped and URL-quoted; links are relative, so the folder works under any path
"""
import argparse
import html
import json
import os
import shutil
import subprocess
import sys
import urllib.parse

from PIL import Image, ImageFile, ImageOps

ImageFile.LOAD_TRUNCATED_IMAGES = True      # a photo cut short by a bad copy years ago still gets its thumbnail, as far as it goes

PIC = (".jpg", ".jpeg", ".png", ".gif")
VID = (".mp4", ".m4v", ".webm", ".mov")
DISPLAY, THUMB = 1600, 360

CSS = """*{box-sizing:border-box}body{margin:0;font-family:system-ui,sans-serif;background:#fafafa;color:#222}
header{padding:1rem 1.5rem;background:#fff;border-bottom:1px solid #ddd}header h1{margin:0;font-size:1.3rem}
header a{color:#06c;text-decoration:none}main{padding:1rem 1.5rem}h2{font-size:1rem;margin:1.2rem 0 .5rem;color:#555}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:.6rem}
.grid a{display:block;background:#eee;border-radius:4px;overflow:hidden;position:relative;aspect-ratio:1/1}
.grid img{width:100%;height:100%;object-fit:cover;display:block}
.grid .name{position:absolute;left:0;right:0;bottom:0;padding:.3rem .5rem;background:rgba(0,0,0,.55);color:#fff;font-size:.85rem}
.grid .play::after{content:"\\25B6";position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:2.2rem;color:#fff;text-shadow:0 0 6px #000}
#lb{position:fixed;inset:0;background:rgba(0,0,0,.92);display:none;align-items:center;justify-content:center;z-index:9}
#lb.on{display:flex}#lb img,#lb video{max-width:96vw;max-height:92vh}#lb .cap{position:fixed;bottom:.5rem;left:0;right:0;text-align:center;color:#ccc;font-size:.9rem}
#lb button{position:fixed;top:50%;background:none;border:0;color:#fff;font-size:2.6rem;cursor:pointer;padding:.5rem 1rem}
#lb .prev{left:0}#lb .next{right:0}#lb .close{top:.3rem;right:.3rem;font-size:2rem}
footer{padding:1rem 1.5rem;color:#888;font-size:.8rem}"""

JS = """(function(){
 var items=[].slice.call(document.querySelectorAll('a[data-full]')),lb,cur=-1;
 if(!items.length)return;
 lb=document.createElement('div');lb.id='lb';
 lb.innerHTML='<button class="close" aria-label="Close">\\u00D7</button><button class="prev" aria-label="Previous">\\u2039</button><div class="stage"></div><button class="next" aria-label="Next">\\u203A</button><div class="cap"></div>';
 document.body.appendChild(lb);
 function show(i){cur=(i+items.length)%items.length;var a=items[cur],st=lb.querySelector('.stage');st.innerHTML='';
  var el;if(a.dataset.kind==='video'){el=document.createElement('video');el.controls=true;el.autoplay=true;el.src=a.dataset.full;}
  else{el=document.createElement('img');el.src=a.dataset.full;el.alt=a.dataset.name||'';}
  st.appendChild(el);lb.querySelector('.cap').textContent=(a.dataset.name||'')+'  ('+(cur+1)+' / '+items.length+')';lb.classList.add('on');}
 function hide(){lb.classList.remove('on');lb.querySelector('.stage').innerHTML='';cur=-1;}
 items.forEach(function(a,i){a.addEventListener('click',function(e){e.preventDefault();show(i);});});
 lb.querySelector('.close').onclick=hide;lb.querySelector('.prev').onclick=function(){show(cur-1);};lb.querySelector('.next').onclick=function(){show(cur+1);};
 lb.addEventListener('click',function(e){if(e.target===lb)hide();});
 document.addEventListener('keydown',function(e){if(cur<0)return;if(e.key==='Escape')hide();else if(e.key==='ArrowLeft')show(cur-1);else if(e.key==='ArrowRight')show(cur+1);});
})();"""


def q(path):
    return "/".join(urllib.parse.quote(p) for p in path.split("/"))


def newer(out, src):
    return os.path.exists(out) and os.path.getmtime(out) >= os.path.getmtime(src)


def make_picture(src, out_full, out_thumb, keep_original, out_orig):
    if newer(out_full, src) and newer(out_thumb, src):
        return
    with Image.open(src) as im:
        im = ImageOps.exif_transpose(im)
        if im.mode not in ("RGB", "L"):
            im = im.convert("RGB")
        full = im.copy()
        full.thumbnail((DISPLAY, DISPLAY), Image.LANCZOS)
        full.save(out_full, "JPEG", quality=85, optimize=True)            # no exif= argument: metadata is dropped
        th = ImageOps.fit(im, (THUMB, THUMB), Image.LANCZOS)
        th.save(out_thumb, "JPEG", quality=80, optimize=True)
    if keep_original:
        shutil.copy2(src, out_orig)


def probe(path):
    try:
        out = subprocess.run(["ffprobe", "-v", "error", "-show_entries", "stream=codec_type,codec_name", "-of", "json", path],
                             capture_output=True, text=True, timeout=60).stdout
        return {s["codec_type"]: s["codec_name"] for s in json.loads(out).get("streams", [])}
    except Exception:
        return {}


def make_video(src, out_video, out_thumb):
    if not newer(out_video, src):
        info = probe(src)
        if src.lower().endswith((".mp4", ".m4v")) and info.get("video") == "h264" and info.get("audio", "aac") == "aac":
            shutil.copy2(src, out_video)
        else:
            try:
                subprocess.run(["ffmpeg", "-y", "-loglevel", "error", "-i", src, "-c:v", "libx264", "-pix_fmt", "yuv420p", "-crf", "23",
                                "-c:a", "aac", "-movflags", "+faststart", out_video], check=True)
            except (subprocess.CalledProcessError, OSError):
                # no usable h264 encoder here (a Cygwin ffmpeg has none): say so, and publish nothing for this file
                print("WARNING: %s is not an h264/aac mp4 and could not be converted (ffmpeg with libx264 is needed); left out" % src,
                      file=sys.stderr)
                return False
    if not newer(out_thumb, src):
        try:
            subprocess.run(["ffmpeg", "-y", "-loglevel", "error", "-ss", "1", "-i", src, "-frames:v", "1", "-vf",
                            "scale=%d:%d:force_original_aspect_ratio=increase,crop=%d:%d" % (THUMB, THUMB, THUMB, THUMB), out_thumb],
                           check=True)
        except (subprocess.CalledProcessError, OSError):
            Image.new("RGB", (THUMB, THUMB), (60, 60, 60)).save(out_thumb, "JPEG")     # no poster frame: a plain tile
    return True


def page(title, rel_root, crumbs, body):
    return ("<!DOCTYPE html>\n<html lang=\"en\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, "
            "initial-scale=1\"><title>%s</title><link rel=\"stylesheet\" href=\"%sstyle.css\"></head><body>"
            "<header><h1>%s</h1></header><main>%s</main><footer>Static gallery.</footer>"
            "<script src=\"%sgallery.js\"></script></body></html>\n"
            % (html.escape(title), rel_root, crumbs, body, rel_root))


def build(src_dir, out_dir, rel, title, keep_orig, stats):
    """One album (SRC/rel -> OUT/rel).  Returns (cover thumbnail path relative to OUT or None)."""
    here_src = os.path.join(src_dir, rel) if rel else src_dir
    here_out = os.path.join(out_dir, rel) if rel else out_dir
    os.makedirs(here_out, exist_ok=True)
    names = sorted(os.listdir(here_src), key=str.lower)
    albums = [n for n in names if os.path.isdir(os.path.join(here_src, n)) and not n.startswith(".")]
    files = [n for n in names if os.path.isfile(os.path.join(here_src, n)) and not n.startswith(".")]
    depth = len(rel.split("/")) if rel else 0
    root = "../" * depth
    cover = None
    cells_albums, cells_items = [], []
    for a in albums:
        sub = (rel + "/" if rel else "") + a
        c = build(src_dir, out_dir, sub, title, keep_orig, stats)
        if c and not cover:
            cover = c
        img = ("<img loading=\"lazy\" src=\"%s\" alt=\"\">" % q(os.path.relpath(os.path.join(out_dir, c), here_out).replace(os.sep, "/"))) if c else ""
        cells_albums.append("<a href=\"%s/\">%s<span class=\"name\">%s</span></a>" % (q(a), img, html.escape(a)))
    for f in files:
        stem, ext = os.path.splitext(f)
        s = os.path.join(here_src, f)
        if ext.lower() in PIC:
            full, th = os.path.join(here_out, stem + ".jpg"), os.path.join(here_out, stem + "-thumb.jpg")
            if os.path.abspath(full) == os.path.abspath(s):
                continue
            try:
                make_picture(s, full, th, keep_orig, os.path.join(here_out, f))
            except Exception as e:                # one unreadable file must not stop the gallery
                print("WARNING: %s could not be read (%s: %s); left out" % (s, type(e).__name__, e), file=sys.stderr)
                stats["skipped"].append(os.path.join(rel, f))
                continue
            stats["pictures"] += 1
            cover = cover or os.path.relpath(th, out_dir).replace(os.sep, "/")
            cells_items.append("<a href=\"%s\" data-full=\"%s\" data-kind=\"image\" data-name=\"%s\"><img loading=\"lazy\" src=\"%s\" alt=\"%s\"></a>"
                               % (q(stem + ".jpg"), q(stem + ".jpg"), html.escape(stem, quote=True), q(stem + "-thumb.jpg"), html.escape(stem, quote=True)))
        elif ext.lower() in VID:
            vid, th = os.path.join(here_out, stem + ".mp4"), os.path.join(here_out, stem + "-thumb.jpg")
            if not make_video(s, vid, th):
                stats["skipped"].append(os.path.join(rel, f))
                continue
            stats["videos"] += 1
            cover = cover or os.path.relpath(th, out_dir).replace(os.sep, "/")
            cells_items.append("<a class=\"play\" href=\"%s\" data-full=\"%s\" data-kind=\"video\" data-name=\"%s\"><img loading=\"lazy\" src=\"%s\" alt=\"\"><span class=\"name\">%s</span></a>"
                               % (q(stem + ".mp4"), q(stem + ".mp4"), html.escape(stem, quote=True), q(stem + "-thumb.jpg"), html.escape(stem)))
        else:
            stats["skipped"].append(os.path.join(rel, f))
    crumbs = "<a href=\"%s\">%s</a>" % (root or "./", html.escape(title))
    parts = rel.split("/") if rel else []
    for i, p in enumerate(parts):
        crumbs += " / " + ("<a href=\"%s\">%s</a>" % ("../" * (len(parts) - 1 - i) or "./", html.escape(p)) if i < len(parts) - 1 else html.escape(p))
    body = ""
    if cells_albums:
        body += "<h2>Albums</h2><div class=\"grid\">%s</div>" % "".join(cells_albums)
    if cells_items:
        body += "<h2>%d items</h2><div class=\"grid\">%s</div>" % (len(cells_items), "".join(cells_items))
    with open(os.path.join(here_out, "index.html"), "w", encoding="utf-8") as fh:
        fh.write(page((html.escape(parts[-1]) + " - " if parts else "") + title, root, crumbs, body))
    stats["albums"] += 1
    return cover


def main():
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    ap.add_argument("--src", required=True, help="folder of album folders")
    ap.add_argument("--out", required=True, help="where the site is written (created)")
    ap.add_argument("--title", default="Gallery")
    ap.add_argument("--originals", action="store_true", help="also copy the original files next to the resized ones")
    ap.add_argument("--clean", action="store_true", help="remove OUT first")
    a = ap.parse_args()
    src, out = os.path.abspath(os.path.expanduser(a.src)), os.path.abspath(os.path.expanduser(a.out))
    if not os.path.isdir(src):
        sys.exit("no such source folder: %s" % src)
    if out == src or out.startswith(src + os.sep) or src.startswith(out + os.sep):
        sys.exit("OUT and SRC must be different, separate folders")
    if a.clean and os.path.isdir(out):
        shutil.rmtree(out)
    os.makedirs(out, exist_ok=True)
    with open(os.path.join(out, "style.css"), "w") as fh:
        fh.write(CSS)
    with open(os.path.join(out, "gallery.js"), "w", encoding="utf-8") as fh:
        fh.write(JS)
    stats = {"albums": 0, "pictures": 0, "videos": 0, "skipped": []}
    build(src, out, "", a.title, a.originals, stats)
    print("built %s: %d album page(s), %d picture(s), %d video(s)%s" % (
        out, stats["albums"], stats["pictures"], stats["videos"],
        ("; skipped (not a picture or video, or a video that could not be converted): %s" % ", ".join(stats["skipped"])) if stats["skipped"] else ""))


if __name__ == "__main__":
    main()
