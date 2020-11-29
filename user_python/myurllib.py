
import socket
import urllib.request, urllib.error, urllib.parse

dns_cache = {}
opener = {}
orig_getaddrinfo = {}
urlopen = urllib.request.urlopen
Request = urllib.request.Request

# 0=host,                 1=port[, 2=family[, 3=socktype[, 4=proto[, 5=flags]]]]
# 0='www.whatwelove.org', 1=80,    2=0,       3=1

# 0=family, 1=socktype, 2=proto, 3=canonname, 4.0=addr,           4.1=port
# 0=2,      1=1,        2=0,     3='',        4.0='192.81.170.2', 4.1=80
def new_getaddrinfo(*args):
    global dns_cache
    global orig_getaddrinfo
    res = orig_getaddrinfo(*args)
    nam = args[0]
    try:
        ip = dns_cache[nam]
        res2 = [(res[0][0], res[0][1], res[0][2], res[0][3], (ip, res[0][4][1]))]
        return res2
    except KeyError:
        dns_cache[nam] = res[0][4][0]
        return res

def dnsinit(dns_map):
    global dns_cache
    global opener
    global orig_getaddrinfo
    global urlopen
    orig_getaddrinfo = socket.getaddrinfo
    socket.getaddrinfo = new_getaddrinfo
    dns_cache = dns_map
    opener = urllib.request.build_opener()
    opener.addheaders = [('User-agent', 'Mozilla/5.0')]
    urllib.request.install_opener(opener)
    urlopen = urllib.request.urlopen

def getmap():
    global dns_cache
    return dns_cache