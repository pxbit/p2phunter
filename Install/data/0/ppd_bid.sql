SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS `ppd_bid`;
CREATE TABLE `ppd_bid` (
  `BidId` int(11) NOT NULL AUTO_INCREMENT,
  `UserId` int(11) NOT NULL,
  `ListingId` int(11) NOT NULL,
  `StrategyId` int(11) NOT NULL,
  `BidAmount` int(11) NOT NULL,
  `BidSN` bigint NOT NULL,
  `BidTime` datetime NOT NULL,
  `RepayStatus` tinyint(1) DEFAULT NULL,
  `UpdateTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`BidId`),
  UNIQUE KEY sn(`BidSN`) 
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


--BidSN is UserId+ListingId+StrategyId
