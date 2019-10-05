#!/usr/bin/env python
# encoding: utf-8
"""
@Version : 1.0
@Time    : 2018/3/14
@Author  : lzc
@function: mysql 数据库操作类
"""

import pymysql

from warnings import filterwarnings
filterwarnings('ignore', category=pymysql.Warning)

#缺省db配置
DB_IP       = '127.0.0.1'
DB_PORT     = 3306
DB_USER     = 'root'
DB_PASSWORD = 'root'
DB_NAME     = 'p2phunter_db_001'
DB_TIMEOUT  = 100


class QueryException(Exception):
    """
    """

class ConnectionException(Exception):
    """
    """

class HtMysql():
    def __init__(self, ip = DB_IP, port = DB_PORT, user = DB_USER, password = DB_PASSWORD,
            dbname=DB_NAME, connect_timeout = DB_TIMEOUT, remote=False, socket=''):
        self.__conn          = None
        self.__cursor        = None
        self.lastrowid       = None
        self.connect_timeout = connect_timeout
        self.ip              = ip
        self.port            = port
        self.user            = user
        self.password        = password
        self.mysocket        = socket
        self.remote          = remote
        self.db              = dbname
        self.rows_affected   = 0

    def __init_conn(self):
        try:
            #print('%s,%d,%s,%s,%s'%(self.ip, self.port,self.user,self.password, self.db))
            conn = pymysql.connect(
                    host            = self.ip,
                    port            = self.port,
                    user            = self.user,
                    passwd          = self.password,
                    db              = self.db,
                    connect_timeout = self.connect_timeout,
                    charset         ='utf8',   #适用缺省的编码模式，否则结果字段是u'xx形式
                    unix_socket     = self.mysocket)
        except pymysql.Error as e:
            raise ConnectionException(e)

        self.__conn = conn

    def __init_cursor(self):
        if self.__conn:
            self.__cursor = self.__conn.cursor(pymysql.cursors.DictCursor)

    def close(self):
        if self.__conn:
            self.__conn.close()
            self.__conn = None

    def query(self, sql, args = None):
        try:
            if self.__conn is None:
                self.__init_conn()
                self.__init_cursor()
            self.__conn.autocommit = True
            self.__cursor.execute(sql, args)
            self.rows_affected = self.__cursor.rowcount
            result = self.__cursor.fetchall()
            return result

        except pymysql.Error as e:
            raise pymysql.Error(e)

        finally:
            if self.__conn:
               self.close()

    def query_many(self, sql, args = None):
        try:
            if self.__conn is None:
                self.__init_conn()
                self.__init_cursor()
            self.__conn.autocommit = True
            self.__cursor.executemany(sql, args)
            self.rows_affected = self.__cursor.rowcount
            result = self.__cursor.fetchall()
            return result

        except pymysql.Error as e:
            raise pymysql.Error(e)

    def exec_txsql(self, sql, args=None):
        try:
            if self.__conn is None:
                self.__init_conn()
                self.__init_cursor()
            if self.__cursor is None:
                self.__init_cursor()

            self.rows_affected=self.__cursor.execute(sql, args)
            self.lastrowid = self.__cursor.lastrowid
            return self.rows_affected

        except pymysql.Error as e:
            raise pymysql.Error(e)

        finally:
            if self.__cursor:
                self.__cursor.close()
                self.__cursor = None

    def commit(self):
        try:
            if self.__conn:
                self.__conn.commit()

        except pymysql.Error as e:
            raise pymysql.Error(e)

        finally:
            if self.__conn:
                self.close()

    def rollback(self):
        try:
            if self.__conn:
                self.__conn.rollback()

        except pymysql.Error as e:
            raise pymysql.Error(e)

        finally:
            if self.__conn:
                self.close()

    def get_lastrowid(self):
        return self.lastrowid

    def get_affectrows(self):
        return self.rows_affected

    def __del__(self):
        if self.__conn:
            self.__conn.close()


