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
  `K7` float default 0,
  `UpdateTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP  ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`StrategyId`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

INSERT INTO `ppd_strategy` VALUES ('4','18月12.5%赔','陪标，年化12.5%利率，期限不超过18个月','12.5','-1','-1',now(),0,0,CURRENT_TIMESTAMP),
('5','24月13%赔','陪标，年化13%利率，期限不超过24个月','13','-1','-1',now(),0,0,CURRENT_TIMESTAMP),
('6','36月15%赔','陪标，年化15%利率，期限36个月','15','-1','-1',now(),0,0,CURRENT_TIMESTAMP),
('10','跻身名流','985名校相关，坏标率极低，推荐打开，但匹配率较低','20','20.4','0.24',now(),0,0,CURRENT_TIMESTAMP),
('11','青春学历','有学历的小鲜肉，初入社会，还比较单纯，匹配率较高，资金量大的推荐打开','20','19.12','0.55',now(),0,0,CURRENT_TIMESTAMP),
('12','大龄本科','本科毕业多年，基本上已成家立业，比较注重信用，推荐打开','20','19.33','0.42',now(),0,0,CURRENT_TIMESTAMP),
('13','青春本科2号','本科毕业，初入职场，信用较好，坏标率低，追求稳定收益的推荐打开','18','18.33','0.2',now(),0,0,CURRENT_TIMESTAMP),
('14','真才实学1号','学历认证信用记录较好，匹配率稍高，资金量大的人推荐打开','20','18.37','0.73',now(),0,0,CURRENT_TIMESTAMP),
('15','本科女2号','本科女，信用等级高，坏标率极低，推荐打开！','18','18.5','0.16',now(),0,0,CURRENT_TIMESTAMP),
('16','青春本科1号','本科毕业，初入职场，信用较好，坏标率低，资金量中等的 推荐打开','20','19.2','0.53',now(),0,0,CURRENT_TIMESTAMP),
('17','年轻硕士','年轻的硕士，附以魔镜评级条件，坏标率极低，推荐打开，但匹配率较低','18','19.17','0',now(),0,0,CURRENT_TIMESTAMP),
('18','负债避峰','借款加欠款，小于最高负债一定比值   本策略出自经验，非机器学习结果，无统计数据','19.5','-1','-1',now(),0,0,CURRENT_TIMESTAMP),
('19','真才实学2号','学历征信认证标，资金量大的人推荐打开','18','17','0.52',now(),0,0,CURRENT_TIMESTAMP),
('20','本科女1号','本科女，信用较好，资金量中等的推荐打开','20','18.7','0.65',now(),0,0,CURRENT_TIMESTAMP),
('21','全面认证','多项关键认证，匹配率低，但收益较高，推荐打开','20','19.6','0.44',now(),0,0,CURRENT_TIMESTAMP),
('22','年轻学历女','匹配率很高，资金量大的可以打开','20','17.6','0.91',now(),0,0,CURRENT_TIMESTAMP);
---------------------------------------------------------------------------------
--	//	ruler: "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1 AND  `GraduateSchool` IN ('清华大学','厦门大学','南京大学','天津大学','浙江大学','西安交通大学','东南大学','上海交通大学','山东大学','中国人民大学','吉林大学','电子科技大学','四川大学','华南理工大学','兰州大学','西北工业大学','同济大学','北京大学','中国科学技术大学','复旦大学','哈尔滨工业大学','南开大学','华中科技大学','武汉大学','中国海洋大学','湖南大学','北京理工大学','重庆大学','大连理工大学','中山大学','北京航空航天大学','东北大学','北京师范大学','中南大学','中国农业大学','西北农林科技大学','中央民族大学','国防科技大学','华东师范大学') AND "
----keep-same1--	//  823 delay_ratio: 0.243013 expect_rate: 20.4144 HDRD: 0.635663
--
----keep--youhua-	//ruler: "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1 AND `StudyStyle` is not null AND `VideoValidate`= 1 AND `Age` BETWEEN 18 and 32"
--	//1060 : ok: 1053 delay: 7 delay_ratio: 0.6763 expect_rate: 17.3816
----keep--youhua-	//ruler: "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1 AND `StudyStyle` is not null AND `Age` BETWEEN 18 and 28 AND `OverdueLessCount`/(`NormalCount`+1)<0.03" 
--	//3831 : ok: 3814 delay:17 delay_ratio: 0.55 expect_rate: 19.12
--
--
----keep--youhua-	//ruler: "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1 AND `EducationDegree`='本科' AND `StudyStyle` IN ('普通','成人','研究生') AND `Age`>32 " 
--	//1168 : ok: 1160 delay: 8 delay_ratio: 0.69 expect_rate: 17.1903 
----keep--youhua-	//ruler: "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1 AND `EducationDegree`='本科' AND `StudyStyle` IN ('普通','成人','研究生') AND `Age`>32 AND `OverdueLessCount`/(`NormalCount`+1)<0.03" 
--	//723: ok: 720 delay: 3 delay_ratio: 0.42 expect_rate: 19.33
--
--	
--
----keep--youhua-	//ruler: "`CurrentRate`=18 AND `Months`= 6 AND `CreditValidate`= 1 AND `EducationDegree`='本科' AND `Age`>=18 AND `Age`<=32 AND `CreditCode` ='B' AND  "
--	//1679 : ok: 1670delay: 9 delay_ratio: 0.532114 expect_rate: 16.02
----keep--youhua-	//ruler: "`CurrentRate`=18 AND `Months`= 6 AND `CreditValidate`= 1 AND `EducationDegree`='本科' AND `Age`>=18 AND `Age`<=32 AND `CreditCode` ='B' AND   `OverdueLessCount`/(`NormalCount`+1)<0.03" 
--	//1000 : ok: 998delay: 2 delay_ratio: 0.2 expect_rate: 18.33
--	public function matchStrategy13($loan_detail_info){
--
--	
----keep--youhua-//ruler: "Months`= 6 AND `CreditValidate`= 1 AND `EducateValidate`= 1 AND "
--	//1740: ok: 3560 delay: 37 delay_ratio: 0.6 expect_rate: 15.8
----keep--youhua//ruler: " `CurrentRate`=20  AND `Months`= 6  AND `CreditValidate`= 1 AND `EducateValidate`= 1 AND  `OverdueLessCount`/(`NormalCount`+1)<0.03 "
--	//1238: ok: 1229 delay: 9 delay_ratio: 0.73 expect_rate: 18.37
--	public function matchStrategy14($loan_detail_info){
--
--	
----deleted -replace-with-r0-//ruler: "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1 AND `NciicIdentityCheck`= 1 AND `CreditCode` IN ('B','C') AND  `VideoValidate`= 1 AND " 
--	//1139 : ok: 1126 delay: 13 delay_ratio: 1.14135 expect_rate: 15.7875 
--	
--	public function matchStrategy15($loan_detail_info){
--
--	}
--
----replace-with-r1--//ruler: "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1 AND `EducationDegree`='本科' AND "
--	//5604 : ok: 5538 delay: 66 delay_ratio: 1.17773 expect_rate: 15.6543
--	
--	public function matchStrategy16($loan_detail_info){
--
--	}
----keep-better--//`CurrentRate`=18 AND `EducationDegree` ='硕士研究生' AND `Months`= 6 AND `CreditCode` IN ('B','C','D') AND `Age`>=18 AND `Age`<=35 AND
--	// 333 : ok: 333 delay: 0 delay_ratio: 0 expect_rate: 19.16 
--	public function matchStrategy17($loan_detail_info){
--
--
----rate: 18 
--
----replace-with-r0 END total:" 636 delay_ratio: 0.157233 expect_rate: 18.5145 HDRD: 0.606723
--ruler:" "`CurrentRate`=18 AND `Months`= 6 AND `CreditValidate`= 1 AND `Gender`= 2 AND `CreditCode` IN ('B','C') AND   `EducationDegree`=BK AND " 
--
--strategy 19:END total:" 768 delay_ratio: 0.520833 expect_rate: 17.0145 HDRD: 0.716254
--ruler:" "`CurrentRate`=18 AND `Months`= 6 AND `CreditValidate`= 1 AND `EducateValidate`= 1 AND " 
--
--
----delete--END total:" 391 delay_ratio: 0.511509 expect_rate: 17.0527 HDRD: 0.736809
--ruler:" "`CurrentRate`=18 AND `Months`= 6 AND `Age`>=24 AND `Age`<=37 AND  `EducationDegree`=YJS AND " 
--
----not use yet-- END total:" 390 delay_ratio: 0.512821 expect_rate: 17.0473 HDRD: 0.737146
--ruler:" "`CurrentRate`=18 AND `Months`= 6 AND  `Gender`= 2 AND `Age`>=24 AND `Age`<=28 AND `StudyStyle` IN (PCY) AND  SCHOOL_211 " 
--
--
--rate: 20 
--
----keep--same1---END total:" 823 delay_ratio: 0.243013 expect_rate: 20.4144 HDRD: 0.635663
--ruler:" "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1 AND  SCHOOL_985 " 
--
--
--strategy-20:END total:" 927 delay_ratio: 0.647249 expect_rate: 18.718 HDRD: 0.73359
--ruler:" "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1  AND `Age`>=24 AND `Age`<=37 AND `Gender`= 2 AND  `EducationDegree`=BK AND " 
--
--
--strategy-22-MID total:" 2742 delay_ratio: 0.911743 expect_rate: 17.6225 HDRD: 0.734497
--ruler:" "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1 AND `Age`>=24 AND `Age`<=37 AND `StudyStyle` IN (PCY) AND `Gender`= 2 AND " 
--
--
--strategy21-END total:" 456 delay_ratio: 0.438596 expect_rate: 19.5903 HDRD: 0.716466
--ruler:" "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1 AND `CertificateValidate`= 1 AND `EducateValidate`= 1 AND "  
--
--END total:" 355 delay_ratio: 0.56338 expect_rate: 19.0678 HDRD: 0.749436
--ruler:" "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1  AND `StudyStyle` IN (PCY) AND `Gender`= 2 AND `Age`>=30 AND `Age`<=37 AND  `EducationDegree`=BK AND " 
--
--
----replace-r1--MID total:" 1051 delay_ratio: 0.53  expect_rate: 19.2 HDRD: 0.73951
--ruler:" "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1  AND `Age`>=24 AND `Age`<=28 AND  `EducationDegree`=BK AND `OverdueLessCount`/(`NormalCount`+1)<0.03 AND " 
--
----strategy23 MID total-update:"  4000 delay_ratio: 0.6 expect_rate: 18.9 HDRD: 0.735701
--ruler:" "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1 AND `CertificateValidate`= 1 AND  `EducationDegree` is not null AND `Age`>=24 AND `Age`<=28 AND `OverdueLessCount`/(`NormalCount`+1)<0.03 AND " 
--
--

---strategy24 MID total:" 1688 delay_ratio: 0.414692 expect_rate: 17.4502 HDRD: 0.658732
--ruler:      ruler:" "`CurrentRate`=18 AND `Months`= 6 AND `CreditValidate`= 1 AND `CertificateValidate`= 1 AND `Gender`= 2 AND `CreditCode` IN ('B','C') AND  " 