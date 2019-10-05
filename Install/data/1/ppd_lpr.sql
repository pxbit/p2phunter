SET FOREIGN_KEY_CHECKS=0;
-- ----------------------------
-- Table structure for `ppd_lpr.sql`
-- ----------------------------
DROP TABLE IF EXISTS `ppd_lpr`;
CREATE TABLE `ppd_lpr` (
`UserId` int(11) NOT NULL,
`StrategyId` int(11) NOT NULL,
`ListingId` int(11) NOT NULL,
`OrderId` tinyint(2) ,
`DueDate` datetime NOT NULL,
`RepayDate` datetime,
`RepayPrincipal` float(6,2) NOT NULL,
`RepayInterest` float(6,2) NOT NULL,
`OwingPrincipal` float(6,2) NOT NULL,
`OwingInterest` float(6,2) NOT NULL,
`OwingOverdue` float(6,2) NOT NULL,
`OverdueDays` int(11),
`RepayStatus` tinyint(1) DEFAULT 0,
`UpdateTime` datetime,
  PRIMARY KEY (`UserId`,`ListingId`,`OrderId`)
) ENGINE=MyISAM AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;