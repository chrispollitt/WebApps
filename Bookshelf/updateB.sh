#!/bin/bash

. /home/Chris/user_bash/Foo_Bar.bash

library="/home/Chris/Documents/Calibre Library"
webdir="/srv/apache2/vhosts/cmlaptop/htdocs-ORIG/books"
host="whatwelove.org"
declare -a up
up=($(foo "$host:sftp"))
user="${up[0]}"
pass="${up[1]}"
remdir="/public_html/books/"
IFS='
'
books="
Allan Botkin/Induced After Death Communication_ A (41)/Induced After Death Communicati - Allan Botkin.epub
Bob Olson/Answers about the Afterlife_ A Priva (37)/Answers about the Afterlife_ A - Bob Olson.epub
Michael Newton/Memories of the Afterlife_ Life Betw (45)/Memories of the Afterlife_ Life - Michael Newton.epub
Nanci Danison/Backwards_ Returning to Our Source f (5)/Backwards_ Returning to Our Sou - Nanci Danison.epub
Nanci Danison/Backwards Beliefs_ Revealing Eternal (58)/Backwards Beliefs_ Revealing Et - Nanci Danison.epub
Natalie Sudman/Application of Impossible Things - M (6)/Application of Impossible Thing - Natalie Sudman.epub
Raymond Moody/Glimpses of Eternity_ Sharing a Love (4)/Glimpses of Eternity_ Sharing a - Raymond Moody.epub
Raymond Moody/Life After Life (56)/Life After Life - Raymond Moody.epub
"

PATH="$PATH:/cygdrive/c/Program Files (x86)/Calibre2"

# 0. manual steps (done in Calibre GUI)
#   a) buy from amazon/borrow from library/dl from internet
#   b) import & strip drm
#   c) convert to EPUB
#   d) fetch-ebook-metadata (choose cover)
#   e) manually clean-up metadata if necessary
#   f) ebook-polish?
#   g) manually clean-up text if necessary

# convert sass to css (stupid css!)
pscss < style.scss > style.css

# loop over books
for book in $books; do
  
  bookdir="${book%/*}"
  bookfile="${book##*/}"
  echo "====$bookfile===="
  
  cd "$library/$bookdir"

  if [[ $1 != putonly ]];then
    # 1. convert books to htmlz and pdf
    ebook-convert.exe "$bookfile" "${bookfile%.*}.htmlz"
    ebook-convert.exe "$bookfile" "${bookfile%.*}.pdf"
    chmod 644 "$bookfile" "${bookfile%.*}."*
  fi
  
  # 2. copy, rename, and expand files
  author=$(perl -lne '/\<dc:creator[^>]*\>(.*?)\</ and print $1' metadata.opf)
  title=$( perl -lne '/\<dc:title[^>]*\>(.*?)\</   and print $1' metadata.opf)
  title="${title%:*}"
  title="${title% - *}"
  if [[ $1 != putonly ]];then
    mv "${bookfile%.*}.pdf" "$webdir/$title - $author.pdf"
    rm -rf   "$webdir/$title - $author"
    mkdir -p "$webdir/$title - $author"
  fi

  cd "$webdir/$title - $author"
  
  if [[ $1 != putonly ]];then
    unzip "$library/$bookdir/${bookfile%.*}.htmlz"
    rm "$library/$bookdir/${bookfile%.*}.htmlz"
    mv "index.html" "contents.html"
    
    # 3. run fixtoc
    php-cli ../fixtocB.php
    
    # 4. run generate-index
    php-cli ../generate-index2B.php > index.html
  fi

  cd ..
  
  if [[ -n $1 ]];then
    # 5. upload to server
    ncftpput -u "$user" -p "$pass"    "$host" "$remdir" "$title - $author.pdf"  
    ncftpput -u "$user" -p "$pass" -R "$host" "$remdir" "$title - $author" 
  fi
done
if [[ $1 != putonly ]];then
  php-cli generate-index1B.php > index.html
fi
if [[ -n $1 ]];then
  for f in index.html noauth.php .wwl/.htaccess; do
    ncftpput -u "$user" -p "$pass" "$host" "$remdir" "$f"
  done
fi

############################
#  
#  ebook-polish.exe [options] input_file [output_file]
#  Note that polishing only works on files in the AZW3 or EPUB formats.
#  (my books are in MOBI format)
#  
#  ebook-convert.exe
#  
#  fetch-ebook-metadata.exe
#
#  formats:
#  * azw
#  * mobi
#  * epub
#  * lit
#  * pdf
#  * htmlz
#  
############################
