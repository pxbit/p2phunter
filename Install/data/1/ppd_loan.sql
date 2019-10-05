SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for `ppd_loan.sql`
-- ----------------------------
DROP TABLE IF EXISTS `ppd_loan`;
CREATE TABLE `ppd_loan` (
`ListingId` int(8) NOT NULL,
  `FistBidTime` datetime,
  `LastBidTime` datetime,
  `LenderCount` smallint(8),
  `AuditingTime` datetime,
  `RemainFunding` float(10,2) NOT NULL,
  `DeadLineTimeOrRemindTimeStr` varchar(16),
  `CreditCode` varchar(4),
  `Amount` float(10,2) NOT NULL DEFAULT '0',
  `Months` tinyint(2) ,
  `CurrentRate` float(6,2) NOT NULL DEFAULT '0',
  `BorrowName` varchar(16) NOT NULL,
  `Gender` tinyint(1) NOT NULL,
  `EducationDegree` varchar(16),
  `GraduateSchool`varchar(32),
  `StudyStyle` varchar(32),
  `Age` tinyint(2) NOT NULL,
  `SuccessCount` smallint(8),
  `WasteCount` smallint(8),
  `CancelCount` smallint(8),
  `FailedCount` smallint(8),
  `NormalCount` smallint(8),
  `OverdueLessCount` smallint(8),
  `OverdueMoreCount` smallint(8),
  `OwingPrincipal` float(10,2),
  `OwingAmount` float(10,2),
  `AmountToReceive` float(10,2),
  `FirstSuccessBorrowTime` datetime,
  `LastSuccessBorrowTime` datetime,
  `RegisterTime` datetime,
  `CertificateValidate` tinyint(1),
  `NciicIdentityCheck` tinyint(1),
  `PhoneValidate` tinyint(1),
  `VideoValidate` tinyint(1),
  `CreditValidate` tinyint(1),
  `EducateValidate` tinyint(1),
  `HighestPrincipal` float,
  `HighestDebt` float,
  `TotalPrincipal` float,
   `RepayStatus` tinyint(1),
   `UpdateTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ListingId`)
) ENGINE=MyISAM AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
-- ----------------------------
-- Records of ppd_loan.sql
--
-- ----------------------------
