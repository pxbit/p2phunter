#!/usr/bin/env python
# encoding: utf-8

"""
@Version : 1.0
@Time    : 2018/3/14
@function:
"""

#import system modules
import sys
sys.path.append("../support/")

import urllib
import time
import datetime
import request as rq
import ht_log as hl

#REMOTE_SERVER_ROOT = 'http://www.127.0.0.1:8080/'
REMOTE_SERVER_ROOT = 'http://localhost:8080/' #测试服务器本地使用
#REMOTE_SERVER_ROOT = 'http://www.p2phunter.cn/'    #测试服务器请求主服使用

REMOTE_SERVER_ROOT = 'http://www.p2phunter.cn/'
LOGGER = init_log('root','INFO','remote.log')

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

def calcHistInterest():
     cookie = cookielib.MozillaCookieJar()
     cookie.load('cookie.txt',ignore_discard=True,ignore_expires=True)
     for item in cookie:
         print 'name:' + item.name + '-value:' + item.value

     handler   = urllib2.HTTPCookieProcessor(cookie)
     opener    = urllib2.build_opener(handler)

     LOGGER.info("requesting user list...")
     post = urllib.urlencode({})
     remoteUrl = REMOTE_SERVER_ROOT + 'home/remote/requestUsers'
     response = opener.open(remoteUrl, post)
     cnt  = response.read()
     df   = pd.read_json(cnt)
     df.columns = ['UserId']
     LOGGER.info("done!")

     LOGGER.info("start request interest calculation...")
     remoteUrl = REMOTE_SERVER_ROOT + 'home/remote/requestInterest'
     finiCnt = 0
     for uid in df.UserId:
         post_data = urllib.urlencode({"uid":uid})
         leftCnt = df.count()[0] - finiCnt - 1
         LOGGER.info("userid " + str(uid) + ", processing..., [" + str(leftCnt) + "] left")
         start    = time.time()
         response = opener.open(remoteUrl, post_data)
         content  = response.read()
         end      = time.time()
         cost     = round(end - start,2)
         LOGGER.info("total " + content.strip('\n') + " records effected! cost " + str(cost) + "s.")
         finiCnt += 1
     LOGGER.info("all users\' historical interest calculated, total " + str(df.count()[0]) + " processed!")

def delHistInterest():
     cookie = cookielib.MozillaCookieJar()
     cookie.load('cookie.txt',ignore_discard=True,ignore_expires=True)
     for item in cookie:
         print 'name:' + item.name + '-value:' + item.value

     handler   = urllib2.HTTPCookieProcessor(cookie)
     opener    = urllib2.build_opener(handler)

     LOGGER.info("requesting user list...")
     post = urllib.urlencode({})
     remoteUrl = REMOTE_SERVER_ROOT + 'home/remote/requestUsers'
     response = opener.open(remoteUrl, post)
     cnt  = response.read()
     df   = pd.read_json(cnt)
     df.columns = ['UserId']
     LOGGER.info("done!")

     LOGGER.info("start request interest deletion...")
     remoteUrl = REMOTE_SERVER_ROOT + 'home/remote/requestDelStatInterest'
     finiCnt = 0
     for uid in df.UserId:
         post_data = urllib.urlencode({"uid":uid})
         leftCnt = df.count()[0] - finiCnt - 1
         LOGGER.info("userid " + str(uid) + ", processing..., [" + str(leftCnt) + "] left")
         start    = time.time()
         response = opener.open(remoteUrl, post_data)
         content  = response.read()
         end      = time.time()
         cost     = round(end - start,2)
         LOGGER.info("total " + content.strip('\n') + " records effected! cost " + str(cost) + "s.")
         finiCnt += 1
     LOGGER.info("all users\' historical interest deleted, total " + str(df.count()[0]) + " processed!")

def calcPhaseOverdue():
     cookie = cookielib.MozillaCookieJar()
     cookie.load('cookie.txt',ignore_discard=True,ignore_expires=True)
     for item in cookie:
         print 'name:' + item.name + '-value:' + item.value

     handler   = urllib2.HTTPCookieProcessor(cookie)
     opener    = urllib2.build_opener(handler)

     LOGGER.info("requesting user list...")
     post = urllib.urlencode({})
     remoteUrl = REMOTE_SERVER_ROOT + 'home/remote/requestUsers'
     response = opener.open(remoteUrl, post)
     cnt  = response.read()
     df   = pd.read_json(cnt)
     df.columns = ['UserId']
     LOGGER.info("done!")

     LOGGER.info("start request phase overdue calculation...")
     remoteUrl = REMOTE_SERVER_ROOT + 'home/remote/requestPhaseOverdue'
     finiCnt = 0
     for uid in df.UserId:
         post_data = urllib.urlencode({"uid":uid, "period":30})
         leftCnt = df.count()[0] - finiCnt - 1

         LOGGER.info("userid " + str(uid) + " period 30 days, processing..., [" + str(leftCnt) + " left]")
         start    = time.time()
         response = opener.open(remoteUrl, post_data)
         content  = response.read()
         end      = time.time()
         cost     = round(end - start,2)
         LOGGER.info("total " + content.strip('\n') + " records effected! cost " + str(cost) + "s.")

         post_data = urllib.urlencode({"uid":uid, "period":90})
         LOGGER.info("userid " + str(uid) + " period 90 days, processing..., [" + str(leftCnt) + " left]")
         start    = time.time()
         response = opener.open(remoteUrl, post_data)
         content  = response.read()
         end      = time.time()
         cost     = round(end - start,2)
         LOGGER.info("total " + content.strip('\n') + " records effected! cost " + str(cost) + "s.")

         finiCnt += 1

def main():
     loginRemote()
     calcHistInterest()
     calcPhaseOverdue()

if __name__ == '__main__':
    main()

