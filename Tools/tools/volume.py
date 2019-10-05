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
import time
import datetime
import ht_request as rq
import ht_log as hl

#REMOTE_SERVER_ROOT = 'http://www.127.0.0.1:8080/'
REMOTE_SERVER_ROOT = 'http://localhost:8080/' #测试服务器本地使用
#REMOTE_SERVER_ROOT = 'http://www.p2phunter.cn/'    #测试服务器请求主服使用
LOGGER = hl.init_log('root','INFO','checkVolume.log')

def  requestVolumelist(url, cookie, days):
     time_now = datetime.datetime.now()
     cnt = 0
     for dayIdx in range(1, days + 1):
         post_data = urllib.urlencode({"dayIdx":dayIdx})
         left_cnt = days - cnt - 1

         req_date = (time_now + datetime.timedelta(days = -dayIdx)).strftime("%Y-%m-%d")
         LOGGER.info("post " + req_date + " volume list, processing..., [" + str(left_cnt) + " left]")
         start    = time.time()

         response = rq.request(url, post_data, cookie)
         content  = response.read()

         end      = time.time()
         cost     = round(end - start, 2)
         LOGGER.info("result: " + content.strip('\n') + "! cost " + str(cost) + "s.")

         cnt += 1

def  requestCheckVolume(url, cookie, days):
     cnt = 0
     time_now = datetime.datetime.now()
     for dayIdx in range(1, days + 1):
         post_data = urllib.urlencode({"dayIdx":dayIdx})
         cnt = days - cnt - 1

         requestDate = (time_now + datetime.timedelta(days = -dayIdx)).strftime("%Y-%m-%d")
         LOGGER.info("post " + requestDate + " volume check, processing..., [" + str(cnt) + " left]")
         start    = time.time()

         response = rq.request(url, post_data, cookie)
         content  = response.read()

         end      = time.time()
         cost     = round(end - start, 2)
         LOGGER.info("result: " + content.strip('\n') + ", cost " + str(cost) + "s.")

         cnt += 1

def main():
     url     = REMOTE_SERVER_ROOT + 'home/remote/login?code=p2phunter_001_code'
     ck_file = "cookie_login.txt"
     cookie  = rq.setcookie(url, ck_file)
     days    = 120

     LOGGER.info("start request volumelist...")
     url = REMOTE_SERVER_ROOT + 'home/remote/requestVolumeList'
     requestVolumelist(url, cookie, days)

     LOGGER.info("start request volume check...")
     url = REMOTE_SERVER_ROOT + 'home/remote/requestCheckVolume'
     requestCheckVolume(url, cookie, days)

if __name__ == '__main__':
    main()

