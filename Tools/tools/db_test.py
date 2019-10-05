#!/usr/bin/env python
# encoding: utf-8
"""
@Version : 1.0
@Time    : 2018/3/14
@Author  : lzc
"""

import sys
sys.path.append("../support/")

import pymysql
import numpy
import pandas as pd
import ht_db as db

def connectDb():
    host     = '127.0.0.1'
    username = 'root'
    password = 'root'
    db       = 'p2phunter_db_001'
    port     = 3306
    db = pymysql.connect(host, username, password, db, port)
    print "connected!"
    return db

def queryDb(db, sql):
    cursor = db.cursor()
    record = []
    try:
        cursor.execute(sql)
        results = cursor.fetchall()
        for row in results:
            bidId = row[0]
            strategyId = row[3]
            record.append([bidId, strategyId])
        return record
    except:
        print "sql" + sql + " excute error"

def closeDb(db):
    db.close()


def main():
    db = connectDb()
    sql = "SELECT * FROM ppd_bid"
    rcd = queryDb(db, sql)
    df  = pd.DataFrame(rcd, columns=['bidId', 'strategyId'])
    grouped = df['bidId'].groupby(df['strategyId'])
    print grouped.count()

if __name__ == '__main__':
    main()



