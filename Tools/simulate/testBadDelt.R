#
# 坏账：这里理解坏账是逾期90天而未归还的金额，逾期的天数计算以在某天00:00:00秒前
# 没有归还为计算依据,拍拍贷以凌晨作为参考，而不是23:59:59秒。
# 其实拍拍贷挺无耻的。
#
calcBadDebt <-function(ref.date, userid)
{
  # 参考日期坏账计算,以926作为测试对象
  lpr.user = subset(lpr, UserId == userid)
  #View(lpr.user926)
  #参考时间
  date.current <- ref.date #格式如:"2018-03-26 00:00:00"
  #筛选出参考时间以前的记录
  rcd.past <- subset(lpr.user, DueDate <= date.current)
  # 筛选出参考时间前第89天的记录，第89天前到期，到参考日期为逾期90日。比如今天到期，那么
  # 只要过了凌晨，就逾期1天，所以这里应该是-89.  
  cdt.baddebt <- (as.Date(rcd.past$DueDate) >= (as.Date(date.current) - 89) & (rcd.past$OverdueDays == 90) & (rcd.past$RepayStatus == 0))
  rcd.baddebt <- subset(rcd.past, cdt.baddebt)
  #View(rcd.baddebt)
  #names(rcd.baddebt)
  #sort(unique(rcd.baddebt$OverdueDays))
  #print(sum(rcd.baddebt$OwingPrincipal))
  return(sum(rcd.baddebt$OwingPrincipal))
}

testBaddelt <- function()
{
  userid <- 926
  ref.date.base <- "2018-03-26 00:00:00"
  for(i in 0:10)
  {
    ref.date <- as.Date(ref.date.base) - i
    res <- calcBadDebt(ref.date, userid)
    cat("bad debt of", as.character(ref.date), ":",res,"\n")
  }
}

testBaddelt()
