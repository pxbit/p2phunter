<?php
namespace Common\Util;
class Pedis {
	const REDIS_HOST = "your redis host";
	const REDIS_PORT = 6379;
	const REDIS_AUTH = "you redis passwd";
	const TestDB = 2;// 
	const BidDB = 3;
	private static $redis_bid = null;
	public static function getBidRedis($timeout = 0)
	{
		if(self::$redis_bid == null){
			self::$redis_bid = new \Redis();
			if (self::$redis_bid->connect(Pedis::REDIS_HOST, Pedis::REDIS_PORT, $timeout))
			{
				self::$redis_bid->auth(Pedis::REDIS_AUTH);
				if(self::$redis_bid->ping() == '+PONG')
				{
					self::$redis_bid->select(Pedis::BidDB);
					return self::$redis_bid;
				}else{
					if(self::$redis_bid)
						self::$redis_bid->close();
				}
			}
			return null;
		}else{
			return self::$redis_bid;
		}
		
	}
}