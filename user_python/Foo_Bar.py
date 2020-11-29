#
# FooBar module
#

import subprocess
from os.path import dirname

fb_path = dirname(dirname(__file__)) + "/user_php"

########################
# get value
#
def foo(one):
    global fb_path
    proc = subprocess.Popen(["php-cli", fb_path+"/Foo_Bar_w.php", one],
        stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    output, err = proc.communicate()
    if proc.returncode == 0:
        # split on newline
        vals = output.split("\n")
        three = vals[0]
        two = vals[1]
    else:
        three = "failed"
        two = "failed"
    return(three, two)

########################
# set value
#
def bar(three, two, one):
    global fb_path
    proc = subprocess.Popen(["php-cli", fb_path+"/Foo_Bar_w.php", three, two, one],
        stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    output, err = proc.communicate()
    return proc.returncode
