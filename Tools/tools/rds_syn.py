#!/usr/bin/env python
# encoding: utf-8
"""
@Version : 1.0
@Time    : 2018/11/01
@Author  : lzc
"""

import sys
sys.path.append("../support/")

import numpy
import pandas as pd
import urllib
import urllib2
import cookielib
import time
import datetime
import request as rq
import ht_log as hl
import const

from sqlalchemy import create_engine
from datetime import datetime


# 常量定义
#const.REMOTE_SERVER_ROOT = 'http://www.127.0.0.1:8080/'
#const.REMOTE_SERVER_ROOT = 'http://localhost:8080/'  #测试服务器本地使用
const.REMOTE_SERVER_ROOT = 'http://www.p2phunter.cn/'
const.REQUEST_RDS_TBL_NAME = 0
const.REQUEST_RDS_RCD_CNT  = 1
const.REQUEST_RDS_RCD_DATA = 2
const.MAX_SYNC_RCD_CNT     = 200000
const.LIMIT = 5000

LOGGER = hl.init_log('root','INFO','rds_syn.log')

def printCookie():
    cookie    = cookielib.CookieJar()
    handler   = urllib2.HTTPCookieProcessor(cookie)
    opener    = urllib2.build_opener(handler)
    remote_url = const.REMOTE_SERVER_ROOT + 'home/remote/login?code=p2phunter_001_code'
    res       = opener.open(remote_url)
    for item in cookie:
       print 'name:' + item.name + '-value:' + item.value

def loginRemote():
    filename  = 'cookie.txt'
    cookie    = cookielib.MozillaCookieJar(filename)
    handler   = urllib2.HTTPCookieProcessor(cookie)
    opener    = urllib2.build_opener(handler)
    remote_url = const.REMOTE_SERVER_ROOT + 'home/remote/login?code=p2phunter_001_code'
    try:
        res = opener.open(remote_url)
        cookie.save(ignore_discard=True,ignore_expires=True)
    except urllib2.URLError, e:
        LOGGER.info(e.reason)


def sync_db():
    # 使用本地保存cookie信息，建立http获取数据的关联对象
    cookie = cookielib.MozillaCookieJar()
    cookie.load('cookie.txt',ignore_discard=True,ignore_expires=True)
    if len(cookie) > 0 :
        LOGGER.info("user logined successfully!")
    else:
        LOGGER.info("user failed to login!")
        return

    cookie = cookielib.MozillaCookieJar()
    cookie.load('cookie.txt', ignore_discard=True, ignore_expires=True)
    handler = urllib2.HTTPCookieProcessor(cookie)
    opener = urllib2.build_opener(handler)


    # 建立本地sqlalchemy引擎
    engine = create_engine('mysql+pymysql://root:root@localhost:3306/p2phunter_db_001?'\
                           'unix_socket=/opt/lampp/var/mysql/mysql.sock&charset=utf8',\
                           encoding='utf-8',convert_unicode=True)

    # 获取RDS所有表名，针对每张表轮流同步
    LOGGER.info("requesting RDS table names...")
    post = urllib.urlencode({"rid": const.REQUEST_RDS_TBL_NAME, "rpara":{} })
    remote_url = const.REMOTE_SERVER_ROOT + 'home/remote/synRds'
    try:
        rsp = opener.open(remote_url)
        df_tbl = pd.read_json( rsp.read() )
        LOGGER.info('done!');

    except urllib2.URLError as e:
        LOGGER.info(e.reason)
        return

    for (index, row) in  df_tbl.iterrows():
        tbl_name = row[0]

        #if (tbl_name == "ppd_lpr"):
        #    continue

        current_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

        # 获取本地 db->table 的记录数目
        sql = 'select count(*) from `%s`'%(tbl_name)
        df = pd.read_sql_query(sql, engine)
        cnt_local = df.iloc[0,0]
        LOGGER.info("local `%s` total record count: %d"%(tbl_name, cnt_local))

        # 开始远程请求, 首先获取RDS 总记录条数, 以确定本地是否和RDS一致
        rq_id = const.REQUEST_RDS_RCD_CNT
        from_time = '0000-00-00 00:00:00'
        to_time = current_time
        # utf-8编码这里是必须的，否则在后端接收到的字符串会带有一个u', 增加处理负担
        rq_para = {
                      "table":tbl_name.encode('utf-8'),
                      "from_time": from_time,
                      "to_time": to_time
                  }
        post = urllib.urlencode({"rid":rq_id, "rpara":rq_para})
        remote_url = const.REMOTE_SERVER_ROOT + 'home/remote/synRds'
        try:
           response = opener.open(remote_url, post)
           cnt_rds = long(response.read())
           LOGGER.info("RDS `%s` total record count: %d"%(tbl_name, cnt_rds))

        except urllib2.URLError as e:
           LOGGER.info(e.reason)
           continue

        # 获取本地 db->table 最近的记录时间戳
        sql = 'select max(RcdTime) from `%s`'%(tbl_name)
        df = pd.read_sql_query(sql, engine)
        rcdtime_local = df.iloc[0,0]
        LOGGER.info("local `%s` latest timestampe: %s"%(tbl_name, rcdtime_local))

        from_time = str(rcdtime_local)
        to_time = current_time
        LOGGER.info("target select range: [%s, %s]"%(from_time, to_time))

        # 开始远程请求RDS记录数增量, 从本地db中最近的时间戳开始，以确定同步范围
        LOGGER.info("requesting RDS `%s` incremental record cnt..."%tbl_name)
        rq_id = const.REQUEST_RDS_RCD_CNT
        # utf-8编码这里是必须的，否则在后端接收到的字符串会带有一个u', 增加处理负担
        rq_para = {
                      "table":tbl_name.encode('utf-8'),
                      "from_time": from_time,
                      "to_time": to_time
                  }
        post = urllib.urlencode({"rid":rq_id, "rpara":rq_para})
        remote_url = const.REMOTE_SERVER_ROOT + 'home/remote/synRds'

        try:
            response = opener.open(remote_url, post)
        except urllib2.URLError as e:
            LOGGER.info(e.reason)
            continue

        cnt_incre_rds = long(response.read())
        LOGGER.info("done! RDS `%s` total new record count: %d"%(tbl_name, cnt_incre_rds))

        # 开始分批同步RDS新增记录
        start = 0
        end = cnt_incre_rds
        if start == end :
            LOGGER.info("local `%s` is the same as RDS, skipped"%(tbl_name))
            LOGGER.info("")
            continue;

        rst = engine.execute("show tables like '%s_swap'"%(tbl_name))
        if len(rst.fetchall()) > 0:
            engine.execute("drop table %s_swap"%(tbl_name))
        else:
            engine.execute("create table %s_swap like %s"%(tbl_name, tbl_name))
            LOGGER.info("local `%s_swap` created"%(tbl_name))

        LOGGER.info("about to fetch RDS `%s` new records from %d to %d"%(tbl_name, start, end))
        while start < end and start < const.MAX_SYNC_RCD_CNT:
            retry_count = 0

            # mysql第一条记录从0开始，所以这里需要注意off-by-one错误
            LOGGER.info("fetching records range:[%d-%d, step %d]" \
                        %(start, start + const.LIMIT - 1, const.LIMIT))

            # 请求RDS记录内容
            rq_id = const.REQUEST_RDS_RCD_DATA
            rq_para = {
                           "table":tbl_name.encode('utf-8'),
                           "from_time": from_time,
                           "to_time": to_time,
                           "start": start,
                           "limit": const.LIMIT
                      }
            post = urllib.urlencode({"rid":rq_id, "rpara":rq_para})
            remote_url = const.REMOTE_SERVER_ROOT + 'home/remote/synRds'

            try:
                ctn =  opener.open(remote_url, post).read()
                # dytype为false, 读出64位长整型的字段时候才不会出错, 比如 ppd_coupon中的sn
                df_rcd = pd.read_json(ctn, dtype=False)

            except urllib2.URLError as e:
                LOGGER.info(e.reason)
                retry_count += 1
                LOGGER.info("retry..., %dth"%(retry_count))

            # 由于RDS可能已经更新, 当前的时间范围内的记录此时可能已经在下一个时间范围,
            # 那么实际取得的记录数据当前时间范围内的记录数，遇到这种情况表明当前表已经
            # 记录内容获取结束.
            cnt_fetched = len(df_rcd)
            if cnt_fetched == 0 :
                break

            LOGGER.info("%d RDS records fetched", cnt_fetched)
            # 保存到本地数据库
            df_rcd.to_sql("%s_swap"%tbl_name, engine, if_exists='append', index= False)
            LOGGER.info("%d new records saved to local `%s`"%(cnt_fetched, tbl_name + "_swap"))

            start += const.LIMIT

        # 将获取到的新的记录更新到数据表
        sql = "select count(*) from %s_swap"%(tbl_name)
        rst = engine.execute(sql)
        if len(rst.fetchall()) > 0 :
            engine.execute("replace into %s select * from %s_swap"%(tbl_name, tbl_name))
            LOGGER.info("merge `%s_swap` to `%s` complete"%(tbl_name, tbl_name))

        LOGGER.info("`%s` synchronized successfully!"%tbl_name)
        engine.execute("drop table %s_swap"%(tbl_name))
        LOGGER.info("`%s_swap` cleared"%tbl_name)
        LOGGER.info("")

    LOGGER.info("p2phunter_db_001 synchronization finished!")


def main():
    loginRemote()
    sync_db()


if __name__ == '__main__':
    main()



