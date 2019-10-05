#!/usr/bin/env python
# encoding: utf-8

"""
@Version : 1.0
@Time    : 2018/3/14
@function:
"""

import sys
sys.path.append("../support/")

import urllib
import urllib2
import cookielib
import time
import datetime
import request as rq
import ht_log as hl


#REMOTE_SERVER_ROOT = 'http://www.127.0.0.1:8080/'
REMOTE_SERVER_ROOT = 'http://localhost:8080/'       #测试服务器本地使用
#REMOTE_SERVER_ROOT = 'http://www.p2phunter.cn/'    #测试服务器请求主服使用
LOGGER = hl.init_log('root','INFO','checkVolume.log')

REMOTE_SERVER_ROOT = 'http://www.p2phunter.cn/'
LOGGER = hl.init_log('root','INFO','pollcache.log')
def printCookie():
     cookie    = cookielib.CookieJar()
     handler   = urllib2.HTTPCookieProcessor(cookie)
     opener    = urllib2.build_opener(handler)
     remoteUrl = REMOTE_SERVER_ROOT + 'home/remote/login?code=p2phunter_001_code'
     res       = opener.open(remoteUrl)
     for item in cookie:
        print 'name:' + item.name + '-value:' + item.value

def loginRemote():
     filename  = 'cookie.txt'
     cookie    = cookielib.MozillaCookieJar(filename)
     handler   = urllib2.HTTPCookieProcessor(cookie)
     opener    = urllib2.build_opener(handler)
     remoteUrl = REMOTE_SERVER_ROOT + 'home/remote/login?code=p2phunter_001_code'
     res       = opener.open(remoteUrl)
     cookie.save(ignore_discard=True,ignore_expires=True)

def  preCache():
     cookie = cookielib.MozillaCookieJar()
     cookie.load('cookie.txt',ignore_discard=True,ignore_expires=True)
     for item in cookie:
         print 'name:' + item.name + '-value:' + item.value

     handler = urllib2.HTTPCookieProcessor(cookie)
     opener  = urllib2.build_opener(handler)

     LOGGER.info("requesting user list...")
     post       = urllib.urlencode({})
     remoteUrl  = REMOTE_SERVER_ROOT + 'home/remote/requestUsers'
     response   = opener.open(remoteUrl, post)
     cnt        = response.read()
     df         = pd.read_json(cnt)
     df.columns = ['UserId']
     LOGGER.info("done!")

     LOGGER.info("start request precache distribution...")
     remoteUrl = REMOTE_SERVER_ROOT + 'home/remote/requestPrecache'
     finiCnt = 0
     for uid in df.UserId:
         post_data = urllib.urlencode({"uid":uid})
         leftCnt = df.count()[0] - finiCnt - 1

         LOGGER.info("userid " + str(uid) + " precache, processing..., [" + str(leftCnt) + " left]")
         start    = time.time()
         response = opener.open(remoteUrl, post_data)
         content  = response.read()
         end      = time.time()
         cost     = round(end - start, 2)
         LOGGER.info("result: " + content.strip('\n') + "! cost " + str(cost) + "s.")

         finiCnt += 1

def main():
     loginRemote()
     preCache()

if __name__ == '__main__':
    main()

