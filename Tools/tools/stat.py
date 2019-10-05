#!/usr/bin/env python
# encoding: utf-8
"""
@Version : 1.0
@Time    : 2018/09/19
@Author  : lzc
"""

import os
import sys
sys.path.append("../support/")

import datetime
import time
import calendar
from   collections import *
import numpy as np
import openpyxl
import pandas as pd
from   ht_db import *
import ht_log as hl
import commands
import requests
import urllib
import urllib2
import json
import const


# 常量定义
#const.REMOTE_SERVER_ROOT = 'http://www.127.0.0.1:8080/'
#const.REMOTE_SERVER_ROOT = 'http://localhost:8080/'  #测试服务器本地使用
#const.REMOTE_SERVER_ROOT = 'http://www.p2phunter.cn/home'
const.REMOTE_SERVER_ROOT = 'http://127.0.0.1/'
const.REQUEST_SAVE_OVERDUE = 0


LOGGER = hl.init_log('root','INFO','overdue_stat.log')

def init_db_local():
    # for LOCAL DEBUG CONFIG,192.168.1.10
    host = 'localhost'
    port = 3306
    username = 'root'
    password = 'root'
    dbname   = 'p2phunter_db_001'
    socket   = "/opt/lampp/var/mysql/mysql.sock"

    db = HtMysql(host, port, username, password, dbname, socket=socket)

    return db

def init_db_remote():
    # for RDS CONFIG
    host = 'your_db_host'
    port = 3306
    username = 'your_name'
    password = 'your_password'
    dbname   = 'your_db'
    socket = ''
    db = HtMysql(host, port, username, password, dbname, socket=socket)
    return db


def fetch_record(db, sql):
    results = db.query(sql)
    try:
        return pd.DataFrame(results)
    except:
        LOGGER.debug("sql '" + sql + "' execution failed, or empty set!")

    return

# src: {0: ppd_bid only, 1:ppd_lpr_only, 2: joinned bid and lpr}
def get_default_sql(src, uid):
    if (src == 0):
        sql = "select UserId, StrategyId, Orderid, RepayInterest, RepayPrincipal, OwingInterest,"\
               "OwingPrincipal, DueDate, RepayStatus, OverdueDays from ppd_lpr where UserId='%d'"%(uid)
    elif(src == 1):
        sql = "select BidId, UserId, ListingId, StrategyId, BidAmount, BidTime, RepayStatus from "\
              "ppd_bid where UserId='%d' and BidTime>='2018-01-01'"%(uid)
    elif(src == 2):
        sql = "select a.UserId, a.StrategyId, a.ListingId, a.OrderId, a.RepayInterest,"\
              "a.RepayPrincipal, a.OwingInterest, a.OwingPrincipal, a.DueDate, a.OverdueDays,"\
              "a.RepayStatus,"\
              "b.BidTime, b.BidAmount from ppd_lpr a "\
              "INNER JOIN ppd_bid b On a.ListingId=b.ListingId and a.UserId=b.Userid where "\
              "a.UserId='{userid}' and b.BidTime>='2018-01-01'"\
              .format(userid=uid)

    return sql

# 下线的策略RepayStatus=-1, 这里不再统计
# 月度分类只统计18年以后的
def calc_avg_period(db, uid, category):
    sql = get_default_sql(2, uid)
    rcd = fetch_record(db, sql)
    if (rcd is None):
        return pd.DataFrame(data=None, columns=['orderid_avg']);

    # 计算平均投资期限
    if (category == 1):
        rcd['month'] = rcd.BidTime.apply(lambda x: x.strftime("%y%m"))
        grp = rcd['OrderId'].groupby(rcd['month'])
    else:
        grp = rcd['OrderId'].groupby(rcd['StrategyId'])

    sum = pd.DataFrame(grp.sum())
    cnt = pd.DataFrame(grp.count())
    avg = sum/cnt
    period = avg.applymap(lambda x: (x * 2 - 1) * 1.02)
    period = np.around(period, decimals=2)
    period.columns = ['orderid_avg']

    return period


def calc_rate(db, uid, category=0):
    avg_month = calc_avg_period(db, uid, category)
    if (avg_month.empty):
        return pd.DataFrame(data=None, columns=['ratio'])

    # 创建用于计算的数据结构，用字典表示，字典键为strategy_id, 值为对应的收益/本金
    if (category == 1):
        sql = get_default_sql(2, uid)
    else:
        sql = get_default_sql(0, uid)

    rcd = fetch_record(db, sql)
    if (rcd is None):
        return pd.DataFrame(data=None, columns=['ratio'])

    if (category == 1):
       rcd['month'] = rcd.BidTime.apply(lambda x: x.strftime("%y%m"))
       grp = rcd.groupby(['month'])
    else:
       grp = rcd.groupby(['StrategyId'])

    ttl_interest = grp['RepayInterest'].sum() + grp['OwingInterest'].sum()
    ttl_principal = grp['RepayPrincipal'].sum() + grp['OwingPrincipal'].sum()
    rate = pd.concat([ttl_interest, ttl_principal, avg_month], axis=1)
    rate.columns = ['interest', 'principal', 'avg_month']
    rate = rate.assign(ratio = np.around(12 * rate['interest'] /
           (rate['principal'] * rate['avg_month'] / 2) * 100, 2))

    return rate['ratio']


"""
# for TEST
def calc_avg_period_sys(db):
    sql = 'select StrategyId, OrderId from ppd_lpr where StrategyId < 10000'
    df = fetch_record(db, sql)
    if (rcd is None):
        return []

    # 计算平均投资期限
    grp = df['OrderId'].groupby(df['StrategyId'])
    sum = pd.DataFrame(grp.sum())
    cnt = pd.DataFrame(grp.count())
    avg = sum/cnt
    period = avg.applymap(lambda x: ((x * 2 - 1) * 1.02))
    period = np.around(period, decimals=2)
    period.columns = ['orderid_avg']

    return period


def calc_rate_sys(db):
    avg_month = calc_avg_period_sys(db)

    # 创建用于计算的数据结构，用字典表示，字典键为strategy_id, 值为对应的收益/本金
    sql = 'select StrategyId,RepayInterest,RepayPrincipal,OwingInterest,OwingPrincipal ' \
          'from ppd_lpr where StrategyId < 10000'
    rcd = fetch_record(db, sql)
    if (rcd is None):
        return []

    grp = rcd.groupby(['StrategyId'])
    ttl_interest = grp['RepayInterest'].sum() + grp['OwingInterest'].sum()
    ttl_principal = grp['RepayPrincipal'].sum() + grp['OwingPrincipal'].sum()
    rate = pd.concat([ttl_interest, ttl_principal, avg_month], axis=1)
    rate.columns = ['interest', 'principal', 'avg_month']
    rate = rate.assign(ratio = np.around(12 * rate['interest']
                       / (rate['principal'] * rate['avg_month'] / 2) * 100, 2))

    return rate['ratio']
"""


def calc_bid_amnt(db,uid, category=0):
    sql = get_default_sql(1, uid)

    rcd = fetch_record(db,sql)
    if (rcd is None):
        return pd.DataFrame(data=None, columns=['BidAmount'])

    if (category == 1):
        rcd['month'] = rcd.BidTime.apply(lambda x: x.strftime("%y%m"))
        bid_amnt = rcd.groupby(['month'])['BidAmount'].sum()
    else:
        bid_amnt = rcd.groupby(['StrategyId'])['BidAmount'].sum()

    return bid_amnt


def calc_bid_cnt(db, uid, category=0):
    sql = get_default_sql(1, uid)
    rcd = fetch_record(db, sql)
    if (rcd is None):
        return pd.DataFrame(data=None, columns=['bidcnt_total', 'bidcnt_repayed'])

    if (category == 1):
        # r = rcd.BidTime.apply(lambda x: str(x)[5:7])
        # BidTime为pandas.Timestamp类型，这里可以直接用函数获得
        rcd['month'] = rcd.BidTime.apply(lambda x: x.strftime("%y%m"))
        bid_cnt_total = rcd.groupby('month')['ListingId'].count()
    else:
        bid_cnt_total = rcd.groupby('StrategyId')['ListingId'].count()

    # 已还清投标数计算
    # ppd投标接口RepaymentService.fetchLenderRepayment的说明:
    # {0：等待还款 1：准时还款 2：逾期还款 3：提前还款 4：部分还款}
    rcd_repayed = rcd.query('RepayStatus>0 & RepayStatus<4')
    if (category == 1):
        bid_cnt_repayed = rcd_repayed.groupby('month')['ListingId'].count()
    else:
        bid_cnt_repayed = rcd_repayed.groupby('StrategyId')['ListingId'].count()

    bid_cnt = pd.concat([bid_cnt_total, bid_cnt_repayed], axis=1)
    bid_cnt.columns = ['bidcnt_total', 'bidcnt_repayed']

    return bid_cnt


def agg_overdue(rcd, days, category=0):
    rcd['month'] = rcd.BidTime.apply(lambda x: x.strftime("%y%m"))
    rcd_delay = rcd.query("(OverdueDays>%d) & (RepayStatus==0)"%(days))
    # 计算延期数据
    if (category == 1):
        prc_delay = rcd_delay.groupby('month')['OwingPrincipal'].sum()

        # 计算延期标个数，由于ListinId可能存在重复，所以必须先做去重操作
        rcd_delay_uni = rcd_delay.drop_duplicates(['ListingId'],keep='first')
        cnt_delay = rcd_delay_uni.groupby(by=['month'])['ListingId'].count()
    else:
        prc_delay = rcd_delay.groupby('StrategyId')['OwingPrincipal'].sum()
        rcd_delay_uni = rcd_delay.drop_duplicates(['ListingId'],keep='first')
        cnt_delay = rcd_delay_uni.groupby(by=['StrategyId'])['ListingId'].count()

    #将日期转化为字符串,这个转换不能忽略，否则后面的比较会出错
    rcd['DueDate'] = rcd['DueDate'].astype('str')
    date = datetime.datetime.now() + datetime.timedelta(days = (0 - days))
    date = date.strftime("%Y-%m-%d")

    # 计算还清数据
    # 如果涉及到查询条件，一定是先查询然后分组，不然部分数据会被合并掉,最后出现误差
    rcd_repay = rcd.loc[lambda x: (x['RepayStatus'] > 0) & (x['DueDate'] < str(date))]
    if (category == 1):
        prc_repay = rcd_repay.groupby(by=['month'])['RepayPrincipal'].sum()
        rcd_repay_uni = rcd_repay.drop_duplicates(['ListingId'],keep='first')
        cnt_repay = rcd_repay_uni.groupby(by=['month'])['ListingId'].count()
    else:
        prc_repay = rcd_repay.groupby(by=['StrategyId'])['RepayPrincipal'].sum()
        rcd_repay_uni = rcd_repay.drop_duplicates(['ListingId'],keep='first')
        cnt_repay = rcd_repay_uni.groupby(by=['StrategyId'])['ListingId'].count()

    # 计算x天前的投标数
    rcd_ttl = rcd.query("BidTime<'%s'"%(str(date)))
    rcd_ttl_uni = rcd_ttl.drop_duplicates(['ListingId'], keep='first')
    if (category == 1):
        cnt_ttl = rcd_ttl_uni.groupby(by=['month'])['ListingId'].count()
    else:
        cnt_ttl = rcd_ttl_uni.groupby(by=['StrategyId'])['ListingId'].count()

    # 有可能完全没有逾期数据，此时提供空的DataFrame
    delay = pd.concat([prc_repay, cnt_repay, prc_delay, cnt_delay, cnt_ttl], axis = 1)
    if (len(delay) > 0):
        delay['delay_rate'] = delay.apply(lambda x: round(
                              100 * x["OwingPrincipal"] / (x["OwingPrincipal"] + x["RepayPrincipal"]),
                              2), axis = 1)
    else:
        delay['delay_rate'] = None

    return delay


def calc_overdue(db, uid, category):
    # 30日逾期率
    # 逾期的两种情况: 1)逾期30日未还  2)逾期30日已还
    sql = get_default_sql(2, uid)
    rcd = fetch_record(db, sql)

    name = [
        "repay_principal30",
        "repay_cnt30",
        "delay_principal30",
        "delay_cnt30",
        "total_cnt30",
        "delay_rate30",
        "repay_principal90",
        "repay_cnt90",
        "delay_principal90",
        "delay_cnt90",
        "total_cnt90",
        "delay_rate90" ]

    if (rcd is None):
        return pd.DataFrame(data=None, columns=name)

    ovd30 = agg_overdue(rcd, 30, category)
    ovd90 = agg_overdue(rcd, 90, category)
    ovd = pd.concat([ovd30, ovd90], axis=1, join='outer')
    ovd.columns=name;

    return ovd


def stat_sys_overdue(db, save_to=0):
    rate = calc_rate_sys(db)
    return rate


# category: {0:策略分类, 1:月度}
# saveto: {0: to_json, 1: to_excel, 2: to_remote}
def stat_user_overdue(db, uid, category=0, save_to=2):
    # 平均投资周期
    thz = calc_avg_period(db, uid, category)

    # 利率
    rate = calc_rate(db, uid, category)

    # 投标金额
    bidamnt = calc_bid_amnt(db, uid,category)

    # 投标数量
    bidcnt = calc_bid_cnt(db, uid, category)

    #  延期率
    ovd = calc_overdue(db, uid, category)

    tbl = pd.concat([thz, rate, bidamnt], axis=1);
    tbl.columns = ['平均投资期限','平均投资利率','已投金额']

    bidcnt.fillna(-1, inplace=True)
    bidcnt['bidcnt_repayed'] = bidcnt['bidcnt_repayed'].astype('int')
    bidcnt['bidcnt_total'] = bidcnt['bidcnt_total'].astype('int')
    bidcnt.replace(-1, 0, inplace=True)

    srs = bidcnt.apply(lambda x: "%s/%s"%(x['bidcnt_repayed'], x['bidcnt_total']), axis=1)
    tbl = pd.concat([tbl, pd.DataFrame(srs, columns=["已还清/投标数"])], axis=1)

    # next item
    tbl = pd.concat([tbl, pd.DataFrame(
        {
           '30日逾期率':ovd['delay_rate30'],
           '90日逾期率':ovd['delay_rate90']
        })], axis = 1)

    # next item
    # 构建临时df, 便于表格显示，这里将非nan的数据由float转换为int, 然后数据格式化至主table
    o = pd.DataFrame(
        {
           '30日逾期标':ovd['delay_cnt30'],
           '投标数':ovd['total_cnt30']
        })

    o.fillna(-1, inplace=True)
    o['30日逾期标'] = o['30日逾期标'].astype('int')
    o.replace(-1, 0, inplace=True)

    srs = o.apply(lambda x: "%s/%s"%(x['30日逾期标'], x['投标数']), axis=1)
    tbl = pd.concat([tbl, pd.DataFrame(srs, columns=['30日逾期标/投标数'])], axis=1)

    # 构建临时df, 便于表格显示，这里将非nan的数据由float转换为int, 然后数据格式化至主table
    o = pd.DataFrame(
        {
            '90日逾期标':ovd['delay_cnt90'],
            '投标数':ovd['total_cnt90']
        })

    o.fillna(-1, inplace=True)
    o['90日逾期标'] = o['90日逾期标'].astype('int')
    o['投标数'] = o['投标数'].astype('int')
    # 无效值或无数据用0替代
    o.replace(-1, 0, inplace=True)
    srs = o.apply(lambda x: "%s/%s"%(x['90日逾期标'], x['投标数']), axis=1)
    tbl = pd.concat([tbl, pd.DataFrame(srs, columns=['90日逾期标/投标数'])], axis=1)

    # 将未替换完毕的无效值，统一填充
    tbl = tbl.fillna(0)

    if (category == 1):
        tbl.insert(0, '月份', tbl.index)
        tbl.sort_values(by='月份', ascending=False, inplace=True)
    elif (category == 0):
        # 将StrategyId作为数据表的第一列,因为to_json并不会写入index
        tbl['StrategyId'] = tbl.index

        # 将StrategyId转换为StrategName
        sql = "SELECT * FROM ppd_strategy"
        rcd = fetch_record(db, sql)
        stg_sys = pd.DataFrame(
            {
                 'StrategyId': rcd['StrategyId'],
                 'StrategyName': rcd['Name'],
                 'status':rcd['status']
            })

        sql = "SELECT * FROM ppd_personal_strategy"
        rcd = fetch_record(db, sql)
        stg_psn = pd.DataFrame(
            {
                'StrategyId': rcd['StrategyId'],
                'StrategyName': rcd['StrategyName'],
                'status':rcd['Status']
            })

        stg = pd.concat([stg_sys, stg_psn], axis=0)
        #print pd.merge(tbl, stg, how='inner', on=['StrategyId'])

        stg.query('status>=0',inplace=True)
        stg.pop('status')
        tbl = pd.merge(tbl, stg, how='inner', on=['StrategyId'])
        # 陪标1000-1999放在最后, 系统策略10-999放在中间, 自定义100000以上放在最前,
        # 各类内按照策略号由大到小排列.
        subtbl_p = tbl.query('StrategyId>1000 & StrategyId<1999')
        subtbl_p.sort_values(by='StrategyId', ascending=False, inplace=True)

        subtbl_s = tbl.query('StrategyId>10 & StrategyId<999')
        subtbl_s.sort_values(by='StrategyId', ascending=False, inplace=True)

        subtbl_z = tbl.query('StrategyId>100000')
        subtbl_z.sort_values(by='StrategyId', ascending=False, inplace=True)
        tbl = pd.concat([subtbl_z, subtbl_s, subtbl_p], axis=0)

        #将StrategyName移动到第一列
        sname = tbl.StrategyName
        tbl = tbl.drop('StrategyId', axis=1)
        tbl = tbl.drop('StrategyName', axis=1)
        tbl.insert(0, 'StrategyName', sname)
        tbl.rename(columns={'StrategyName':'策略名'},inplace=True)

    ctg_name = np.where((category == 0), "stg", "mth")
    if (save_to == 0):
        name = "uid%d_tbl_ovd_%s.json"%(uid, ctg_name)
        tbl.to_json(name, orient='records')

        # 获取cookie
        # url = const.REMOTE_SERVER_ROOT + '/home/remote/login?code=p2phunter_001_code'
        # res = requests.get(url)
        # # 请求远端保存数据
        # url = const.REMOTE_SERVER_ROOT +"/home/remote/saveData"
        # files = {'file': open(name, 'rb')}
        # res = requests.post(url, files=files, cookies=res.cookies)
        # print res.text

    elif (save_to == 1):
        name = "uid%d_tbl_ovd_%s.xlsx"%(uid, ctg_name)
        writer = pd.ExcelWriter(name)
        tbl.to_excel(writer,'page_1', float_format='%.2f')
        writer.save()

    else:
        # 获取cookie
        url = const.REMOTE_SERVER_ROOT + '/home/remote/login?code=p2phunter_001_code'
        res = requests.get(url)

        # 请求远端保存
        name = "uid%d_tbl_ovd_%s.json"%(uid, ctg_name)
        data = {"rid": const.REQUEST_SAVE_OVERDUE,
                "rpara": {
                    "filename": name.encode('utf-8'),
                    "ovd": tbl.to_json(orient='records')
                }
               }
        url = const.REMOTE_SERVER_ROOT +"/home/remote/saveData"
        payload = {"device":"gabriel","data_type":"data","zone":1,"sample":4,"count":0,"time_stamp":"00:00"}
        rsp = requests.post(url, data=({"payload": json.dumps(data)}), cookies=res.cookies)
        LOGGER.info("%s"%(rsp.text.strip('\n')))

    if save_to != 2 :
        # 设置保存至本地路径
        cwd_path = os.getcwd()
        udt_path = cwd_path + "/../data/statistics/overdue"

        # 将生成的文件拷贝的指定的路径
        os.system("mv -f ./*.json %s"%(udt_path))


def get_userlist(db):
    sql = "SELECT UserId,UserName FROM ppd_user where RTExpireDate >'0000-00-00 00:00:00'"
    rcd_user = fetch_record(db, sql)
    rcd_user.index = rcd_user['UserId']

    sql = "SELECT BidId,UserId FROM ppd_bid"
    rcd_bid = fetch_record(db, sql)
    rcd_bid_user = rcd_bid.groupby(by=['UserId'])['BidId'].count()
    rcd_bid_user.sort_values(ascending=False, inplace=True)

    sql = "SELECT UserId,ListingId FROM ppd_lpr group by ListingId"
    rcd_lpr = fetch_record(db, sql)
    rcd_lpr_user = rcd_lpr.groupby(by=['UserId'])['ListingId'].count()

    return pd.concat([rcd_user, rcd_bid_user, rcd_lpr_user], axis=1).dropna().index


def main():
    db = init_db_local()

    LOGGER.info("begin to get target users list...")
    uid_list = get_userlist(db)
    #uid_list = [1,1248, 292]
    LOGGER.info("done!")

    ttl_cnt = len(uid_list)
    cnt = 0
    for uid in uid_list:
        left_cnt = ttl_cnt - cnt - 1
        LOGGER.info("calculating user %d ..., %d users left."%(uid, left_cnt))
        start = time.time()
        # 计算数据
        stat_user_overdue(db, uid, category=0)
        stat_user_overdue(db, uid, category=1)

        end  = time.time()
        cost = round(end - start, 2)
        LOGGER.info("success: cost " + str(cost) + "s.")

        cnt += 1



if __name__ == '__main__':
    main()



