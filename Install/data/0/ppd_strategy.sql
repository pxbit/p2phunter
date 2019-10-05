SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS `ppd_strategy`;
CREATE TABLE `ppd_strategy` (
  `StrategyId` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(256) NOT NULL,
  `Discription` varchar(256) DEFAULT NULL,
  `BidRate` float NOT NULL,
  `ExpectRate` float NOT NULL,
  `DelayRate` float NOT NULL,
  `CreatTime` datetime NOT NULL,
  `status` int  default 0,
  `UpdateTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP  ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`StrategyId`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

