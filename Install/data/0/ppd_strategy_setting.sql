SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS `ppd_strategy_setting`;
CREATE TABLE `ppd_strategy_setting` (
  `SettingId` int(11) NOT NULL AUTO_INCREMENT,
  `UserId` varchar(256) NOT NULL,
  `StrategyId` varchar(256) DEFAULT NULL,
  `BidAmount` int(11),
  `UpdateTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`SettingId`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

