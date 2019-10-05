#!/usr/bin/env python
# encoding: utf-8

"""
@Version : 1.0
@request : 2018/9/02
"""

import urllib2
import urllib
import cookielib


def setcookie(url, fn):
     cookie    = cookielib.MozillaCookieJar(fn)
     handler   = urllib2.HTTPCookieProcessor(cookie)
     opener    = urllib2.build_opener(handler)
     response  = opener.open(url)
     cookie.save(ignore_discard=True,ignore_expires=True)
     return cookie

def request(url, data, cookie):
     handler = urllib2.HTTPCookieProcessor(cookie)
     opener  = urllib2.build_opener(handler)
     rsp     = opener.open(url, data)
     return rsp


