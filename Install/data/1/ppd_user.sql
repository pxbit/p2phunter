SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS `ppd_user`;
CREATE TABLE `ppd_user` (
  `UserId` int(11) NOT NULL AUTO_INCREMENT,
  `UserName` varchar(256) NOT NULL,
  `PassWord` varchar(256) DEFAULT NULL,
  `PhoneNumber` int(11) DEFAULT 0,
  `Score` int(11) DEFAULT 0,
  `AccessToken` char(36),
  `RefreshToken` char(36),
  `OpenID` char(32),
  `UserBalance` int(11) DEFAULT 0,
  `UBUpdateTime`  datetime DEFAULT null,
  `ATExpireDate` datetime,
  `RTExpireDate` datetime,
  `CreateTime` datetime,
  `UpdateTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`UserId`),
  UNIQUE KEY unm(`UserName`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


INSERT INTO `ppd_user` values(10,'system_bidall','0',0,0,'0','0','0',1000000,'2017-06-19 13:28:26','2017-06-19 13:28:29','2017-06-19 13:28:32','2017-06-19 13:28:34','0');