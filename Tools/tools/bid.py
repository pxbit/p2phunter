#!/usr/bin/env python
# encoding: utf-8

"""
@Version : 1.0
@Time    : 2018/3/14
@Author  : lzc
@function: mysql 数据库操作类
"""

import numpy
import pandas as pd
from db import MysqlHunter

def main():
     db = MysqlHunter()
     sql = "SELECT a.ListingId, a.StrategyId, b.OrderId, b.DueDate, b.RepayDate, b.OverdueDays,
             b.RepayStatus FROM ppd_bid a left join ppd_lpr b on a.ListingId=b.ListingId"
     rcd = db.query(sql)
     df  = pd.DataFrame(rcd)
     rcd
     print df

if __name__ == '__main__':
    main()

