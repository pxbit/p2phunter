#!/usr/bin/env python
# encoding: utf-8
"""
@Version : 1.0
@Time    : 2018/04/06
@Author  : lzc
@function: 时间相关
"""

import time

def getSysTime():
    return time.strftime("%Y-%m-%d %H:%M:%S",time.localtime(time.time()))
