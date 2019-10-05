#!/usr/bin/env python
# encoding: utf-8
"""
@Version : 1.0
@Time    : 2018/04/07
@Author  : lzc
@function: log相关
"""
import logging
import logging.handlers

LOG_LEVELS = {'DEBUG': logging.DEBUG,
              'INFO': logging.INFO,
              'ERROR': logging.ERROR,
              'CRITICAL': logging.CRITICAL }
#日志的输出的格式
LOGGING_FORMAT = '%(asctime)s - [%(filename)s:%(lineno)3d] - [%(levelname)s]: %(message)s'
#时间格式 2016-06-14 10:10:00
STANDARD_DATE_FORMAT = '%Y-%m-%d %H:%M:%S'
#当达到50M就进行切分
DEFAULT_LOG_MAX_SIZE = 50 * 1024 * 1024


def init_log(logger_name,
             level='DEBUG',
             logfile='/tmp/logtest.log',
             formatter=LOGGING_FORMAT,
             max_size=DEFAULT_LOG_MAX_SIZE):
    logger = logging.getLogger(logger_name)
    logger.setLevel(LOG_LEVELS[level])
    #定义日志输出到文件的handler ，也可以定义 fh=logging.FileHandler(logfile)
    fh = logging.handlers.RotatingFileHandler(logfile, maxBytes=max_size, backupCount=3)
    fh.setLevel(logging.DEBUG)
    #定义日志输出到终端的handler
    ch = logging.StreamHandler()
    ch.setLevel(logging.INFO)
    formatter = logging.Formatter(formatter)
    fh.setFormatter(formatter)
    ch.setFormatter(formatter)
    logger.addHandler(fh)
    logger.addHandler(ch)
    return logger
