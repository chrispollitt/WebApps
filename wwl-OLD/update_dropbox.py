#!/home/whatwelo/bin/python3
import sys
import time
import contextlib
from os.path import dirname, basename, join as path_join
sys.path.append('/home/whatwelo/user_python')
from dropbox import Dropbox
from dropbox.files import WriteMode
from Foo_Bar import foo

@contextlib.contextmanager
def stopwatch(message):
  """Context manager to print how long a block of code took."""
  t0 = time.time()
  try:
    yield
  finally:
    t1 = time.time()
    print('Total elapsed time for %s: %.3f' % (message, t1 - t0))

def main():
  (user, pasw) = foo('dropbox')
  
  lroot = "/home/whatwelo/local/usr/ssl/LetsEncrypt"  # local
  rroot = "/Chris/ssl/LetsEncrypt"                    # remote (dropbox)
  files = (
    "certs/whatwelove.org.crt",
    "certs/whatwelove.org.key",
    "ca/signing-ca-chain1.pem",
    "ca/signing-ca-chain2.pem",
    "ca/signing-ca-chain.pem"   # copy as dropbox does not support symlinks
  )
  
  dbx = Dropbox(pasw)
  
  print ("Attempting to upload...")
  for filen in files:
    try:
      base = basename(filen)
      dirn = dirname(filen)
      src = path_join(lroot, dirn, base)
      dst = path_join(rroot, dirn, base)
      print('Uploading %s to dropbox:%s' % (src, dst))
      with open(src, 'rb') as f:
        data = f.read()
      with stopwatch('upload %d bytes' % len(data)):
        dbx.files_upload(
          data,
          dst,
          mode=WriteMode('overwrite', None),
          autorename=False,
          mute=True
        )
    except Exception as err:
      print(("Failed to upload %s\n%s" % (filen, err)))
  print("Finished upload.")

main()

"""
######################################

Notes:

Do not run on cmlaptop, only cs16.uhcloud.com

"""
