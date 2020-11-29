#!/home/whatwelo/bin/python3

from dropbox import Dropbox
from dropbox.files import WriteMode
from Foo_Bar import foo
from os.path import dirname, basename, join as path_join

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
        with open(src) as f:
            dbx.files_upload(
                f,
                dst,
                mode=WriteMode('overwrite', None),
                autorename=False,
                mute=True
            )
    except Exception as err:
        print(("Failed to upload %s\n%s" % (file, err)))
print("Finished upload.")

"""
######################################

Notes:

Do not run on cmlaptop, only cs16.uhcloud.com

"""
