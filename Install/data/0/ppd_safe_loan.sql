SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS `ppd_safe_loan`;
CREATE TABLE `ppd_safe_loan` (
`ListingId` int(8) NOT NULL,
  `Title` varchar(128),
  `CreditCode` varchar(4),
  `Amount` float(10,2) NOT NULL DEFAULT '0',
  `Rate` float(6,2) NOT NULL DEFAULT '0',
  `Months` tinyint(2) ,
  `Payway` tinyint(2),
  `RemainFunding` float(10,2) NOT NULL DEFAULT '0',
   `UpdateTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ListingId`)
) ENGINE=MyISAM AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
