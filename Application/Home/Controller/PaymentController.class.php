<?php
namespace Home\Controller;
use Think\Controller;
class PaymentController extends BaseController {

    private $baduser=array();
    
    public function _initialize(){
        set_time_limit(0);
        ob_implicit_flush(1);
        echo str_repeat(" ", 2000);
    }
    

    private  function isBackCouponAdded($userid)
    {
        $month_begin_date = date("Y-m")."-01 00:00:00";
        $m=M("coupon");
        $status = $m->where("ObtainDate >'2017-12-01 00:00:00' and UserId = '{$userid}' and Type='2'")->find();
        if($status === null){
            return false;
        }else{
            if($status ===false){
                $this->ppdLog("isBackCouponAdded find coupon error!");
            }
            return true;
        }
    }
    private function addCostBackCoupon($userid,$score)
    {
        $now = date("Y-m-d H:i:s");
        $coupon['UserId']=$userid;
        $coupon['TotalQuota']=$score;
        $coupon['ObtainDate']=$now;
        $coupon['ExpireDate']=$now;
        $coupon['UseDate']=$now;
        $coupon['Reason']="提前还款返还";
        $coupon['SN']=$this->couponSN(2,$score);
        $coupon['Used']=1;
        $coupon['Type']=2;
        $m=M("coupon");
        $status=$m->add($coupon);
        if($status===false){
            $this->ppdLog("addCostBackCoupon DB ADD ERROR");
            return false;
        }else{
            return true;
        }
    }

    /* scan lpr and bid record pay back to user the cost on loans that be repayed one time very earlier.*/
    public function startCostBack()
    {
        $join = "JOIN ppd_bid ON  ppd_lpr.UserId=ppd_bid.UserId and ppd_lpr.ListingId = ppd_bid.ListingId ";
        $sd = date("Y-m",strtotime("-1 Month",time())) . "-01";
        $ed = date("Y-m",time()) . "-01";
        $datestart="$sd 00:00:01";
        $dateend="$ed 00:00:00";
        $lpr= M("lpr");
        $users=$lpr->join($join)->where("BidTime BETWEEN '{$datestart}' and '{$dateend}' and OrderId = 1 and ppd_lpr.RepayStatus = 3 AND OverdueDays<-20
        and BidCost>0 and ppd_lpr.RepayPrincipal = ppd_bid.BidAmount ")->distinct('true')->field('ppd_lpr.UserId')->select();
        if($users===false){
            $this->ppdLog("getAllOnceRepayLessThan10Days ERROR:" . $lpr->getDbError());
        }else if($users===null){
            $this->ppdLog("getAllOnceRepayLessThan10Days get none. oops!");
        }else{
            foreach ($users as $user){
                $userid= $user['UserId'];
                if($this->isBackCouponAdded($userid)){
					$this->ppdLog("startCostBack: USER:$userid already add feed back cost coupon");
                }else{
					$backCost=$this->getAllOnceRepayLessThan10Days($userid, $sd, $ed);
					if($backCost>0){
                        $status = $this->addCostBackCoupon($userid, $backCost);
                        if($status){
                            $user = M("user");
                            $result = $user->where("UserId = '{$userid}'")->setInc("Score",$backCost);
                            if($result===false){
                                $this->ppdLog("$userid: score back $backCost not success increased.",3);
                                return "数据库错误，请联系管理员！";
                            }
                        }

						echo "add:$userid:$backCost</br>";
					}else{
						echo "not add:$userid:$backCost</br>";
					}
				}
			}
        }
    }

    private function getAllOnceRepayLessThan10Days($userid,$sd,$ed)
    {
        /*
         * SELECT * FROM ppd_lpr JOIN ppd_bid ON  ppd_lpr.UserId=ppd_bid.UserId and ppd_lpr.ListingId = ppd_bid.ListingId  WHERE
        `BidTime`  > '2017-11-01 00:00:00' and `BidTime` <'2017-12-01 00:00:00' and OrderId = 1 and ppd_lpr.RepayStatus = 3 AND OverdueDays<-20
        and BidCost>0 and `ppd_lpr`.`RepayPrincipal` = `ppd_bid`.`BidAmount`
        */
            $join = "JOIN ppd_bid ON  ppd_lpr.UserId=ppd_bid.UserId and ppd_lpr.ListingId = ppd_bid.ListingId ";
            $datestart="$sd 00:00:01";
            $dateend="$ed 00:00:00";
            $lpr= M("lpr");
            $totalCost=$lpr->join($join)->where("BidTime BETWEEN '{$datestart}' and '{$dateend}' and OrderId = 1 and ppd_lpr.RepayStatus = 3 AND OverdueDays<-20
            and BidCost>0 and ppd_lpr.RepayPrincipal = ppd_bid.BidAmount and ppd_lpr.UserId = '{$userid}'")->sum('BidCost');
            if($totalCost===false){
                $this->ppdLog("getAllOnceRepayLessThan10Days ERROR:" . $lpr->getDbError());
            }
            else if($totalCost===null){
                echo  "$userid:[$sd-$ed] 0";
                return 0;
            }else{
                echo  "$userid:[$sd-$ed] $totalCost";
                return $totalCost/2;
            }
    }

    
    public function isExsitInLPR($id)
    {
        $lpr=M("lpr");
        $data=$lpr->where("ListingId='{$id}'")->find();
        if($data===false){
            $this->ppdLog("DB ERROR:" . $lpr->getDbError());
            return true;
        }else if($data){
            return true;
        }else 
            return false;
    }
    
	private function updateBidTimeStamp($user_id,$id)
	{
        $bid=M("bid");
        $data['RepayStatus']=0;
        $data['UpdateTime']= date("Y-m-d H:i:s", time());
        $status=$bid->where("UserId='{$user_id}' AND ListingId='{$id}'")->save($data);
        if($status===false){
            $this->ppdLog("markBidFinishRepay update bid ERROR:".$bid->getDbError());
        }
    }
    
    
    private function markBidFinishRepay($user_id,$id)
    {
        $bid=M("bid");
        $data['RepayStatus']=1;
        $status=$bid->where("UserId='{$user_id}' AND ListingId='{$id}'")->save($data);
        if($status===false){
            $this->ppdLog("markBidFinishRepay update bid ERROR:".$bid->getDbError());
        }
        $lpr = M("lpr");
        $status =$lpr->where("UserId='{$user_id}' AND ListingId = '{$id}' and RepayStatus = 0")->delete();
        if($status===false){
        	$this->ppdLog("markBidFinishRepay update lpr ERROR:".$lpr->getDbError());
        }
        
    }
    public function checklpr(){
    	$lpr = M("lpr");
    	$datalist =$lpr->where("RepayStatus = 3")->field("ListingId,UserId")->limit(100)->select();
    	if($datalist===false){
    		$this->ppdLog("checklpr qry lpr ERROR:".$lpr->getDbError());
    	}else{
    		foreach ($datalist as $data){
    			$user_id = $data['UserId'];
    			$id = $data['ListingId'];
    			$status =$lpr->where("UserId = '{$user_id}' AND ListingId = '{$id}' and RepayStatus = 0")->delete();
    			if($status === false){
    				$this->ppdLog("checklpr del lpr UserId: $user_id ListingId:$id :ERROR:".$lpr->getDbError());
    			}else if($status){
    				$this->ppdLog("Checklpr delete UserId: $user_id ListingId:$id total:$status!");
    			}
    		}
    	}
    }





    public function run(){
    }
    
    /* crontab function once a day */
    public function SyncUser(){
    	$user_info = M("user");
    	$user_status = M("user_monitor");
    	$users = $user_info->where(1)->field("UserId, Score, ATExpireDate, RTExpireDate")->select();
    	$one_day_before = date("Y-m-d H:i:s",time() - 24*3600);
    	if( $users == null){
    		$this->ppdLog("SyncUser User sheet Access Failed");
    	}elseif($users){
    		foreach($users as $user){
                $user_id =  $user['UserId'];
                $user_monitor['UserId'] = $user_id;
                $userm = $user_status->where("UserId = '{$user_id}'")->find();
                if ($userm){
                       $user_monitor = $userm;
                }else{
                       $this->ppdLog("SyncUser " . $user['UserId'] . " not found in monitor, maybe new user or db error");
                }
    			if ($user['Score'] == 0 || $user['ATExpireDate'] < $one_day_before)
    			{
    				$user_monitor['Status'] = -1;
    			}else{
    				$user_monitor['Status'] = 0;
    			}
    			$status = $user_status->add($user_monitor, array(), True);
    			if($status == null ){
    				$this->ppdLog("SyncUser User add to monitor failed! UserId:" . $user['UserId']);
    			}
    		}
    		$userm = $user_status->where(1)->field("UserId")->select();
    		if ($userm == null){
    			$this->ppdLog("SyncUser User_monitor sheet Access Failed");
    		}else if ($userm){
    			foreach($userm as $um){
    				$updated = 0;
    				foreach($users as $user){
    					if($user['UserId'] == $um['UserId']){
    						$updated = 1;
    						break;
    					}
    				}
    				if($updated == 0){
    					$um['Status'] = -1;
    					$status = $user_status->add($um, array(), True);
    					if($status == null ){
    						$this->ppdLog("SyncUser User add to monitor failed! UserId:" . $user['UserId']);
    					}
    				}
    				
    			}
    		}
    	}else{
    		echo "null";
    	}
    	echo "finish!";
    }
    
    public function updateLpr($token, $listing_id, $user_id, $strategy_id){
    	$repay_plan = $this->getLenderRepayment($token, $listing_id);
    	if($repay_plan){
    		$repay_status=true;
    		foreach ( $repay_plan as $rp){
    			$rp['UserId']=$user_id;
    			$rp['StrategyId']=$strategy_id;
    			$rp['UpdateTime']=date("Y-m-d H:i:s",time());
    			$lpr=M("lpr");
    			$status=$lpr->add($rp,array(),true);
    			if($status===false){
    				$this->ppdLog("add lpr record ERROR:". $lpr->getDbError(),3);
    			}
    			if($rp['RepayStatus']==0)
    				$repay_status=false;
    		}
    		if($repay_status){
    			$this->markBidFinishRepay($user_id, $listing_id);
            }else{
                $this->updateBidTimeStamp($user_id, $listing_id);
            }
    			
    		return true;
    	}else{
    		return $repay_plan;
    	}
    }
    public function UpdateLivingInMinutes($minitues = 5)
    {
       $um = M("user_monitor");
       $timeline = date("Y-m-d H:i:s",time() - $minitues*60);
       $users = $um->where("LastUpdate > '{$timeline}'")->find();
       if($users)
               return true;
       else
               return false;
    }
    
    /* for users in user_monitor with status 0, check user valid and update repayment */
    /* add to crontab */
    public function update(){
        if($this->UpdateLivingInMinutes(5)){
			echo "still alive";
			$this->ppdLog("update user Repayment is in progress!");
			return;
        } 
    	$um = M("user_monitor");
    	$ui = M("user");
    	$now = date("Y-m-d H:i:s",time());
    	$yestoday = date("Y-m-d H:i:s",time() - 24*60*60);
    	$users = $um->where("Status = 0")->order("LastUpdate asc")->limit(1)->select();
    	if($users ===false){
    		$this->ppdLog("update User Repayment failed! DB ERROR");
    	}else{
	    	foreach($users as $user){
	    		//check user valid
	    		$userid = $user['UserId'];
	    		$userinfo = $ui->where("UserId = '{$userid}'")->find();
	    		if($userinfo===false){
	    			$this->ppdLog("update User Repayment failed! DB ERROR");
	    		}else if($userinfo == false){
	    			$this->ppdLog("BUG FOUND USERID: $userid found in user_monitor but not found in user");
	    		}else if($userinfo['ATExpireDate'] < $now){
	    			$this->updateMonitor($userid);
	    			continue;// INVALID USER
	    		}else{
	    			$token = $userinfo['AccessToken'];
	    			//check whether user token valid
	    			$status = $this->getBalance($token);
	    			if ($status >= 0){//TOKEN OK
	    				//get bid list
	    				$this->updateMonitor($userid);
	    				$bid = M("bid");
                        $bidrecord = $bid->where("UpdateTime < '{$yestoday}' and UserId = '{$userid}' and (RepayStatus is null or RepayStatus != 1)")->order("UpdateTime asc")->select();
                        if($bidrecord === false){
	    					$this->ppdLog("update User Repayment  get bidrecord failed! DB ERROR");
	    				}else if($bidrecord){
	    					$this->ppdLog("Updating repayment for user {$userid} ");
	    					foreach ($bidrecord as $record){
								if(!$this->updateLpr($token, $record['ListingId'], $record['UserId'], $record['StrategyId']))
								{
									$this->ppdLog("updateLpr failed " . json_encode($record));
									$this->updateMonitor($userid, 1);
								}else{
									$this->updateMonitor($userid);
								}
	    						usleep(100000);
	    					}
	    					// mark user finished
	    					$this->updateMonitor($userid);
	    				}else{
	    					$this->ppdLog("{$userid} has no bid record!! ", 1);
	    				}
	    			}else if ($status == -400){//TOKEN INVALID
	    				if ($this->updateMonitor($userid, 100) > 200)
	    					$this->markBadUser($userid);
	    			}else{
	    				$this->updateMonitor($userid, 1);
	    			}
	    		}
	    	}
    	}
    	
    }
    
    private function updateMonitor($userid, $fail_count = 0){
    	$failCount = 0;
    	$um = M("user_monitor");
    	$user = $um->where("UserId = '{$userid}'")->find();
    	if ($user === false){
    		$this->ppdLog("updateMonitor DB ERROR", 3);
    	}else if($user == false){
    		$this->ppdLog("User '{$userid}' not found", 2);
    	}else{
    		if($fail_count == 0){
    			$user['FailCount'] = 0;
    		}else{
    			$user['FailCount'] = $user['FailCount'] + $fail_count;
    			$failCount = $user['FailCount'];
    		}
    		$user['LastUpdate'] = date("Y-m-d H:i:s",time());
    		$status = $um->save($user);
    		if($status === false){
    			$this->ppdLog("updateMonitor DB save status ERROR", 3);
    		}else if($status == false){
    			//$this->ppdLog("updateMonitor no info updated, userid:{$userid}", 2);
    		}
    	}
    	return $failCount;
    	
    }
	private function markBadUser($user_id)
	{
		$bid = M("bid");
		$data['RepayStatus'] = -1;
		$status = $bid->where("UserId = '{$user_id}' AND (RepayStatus is  null OR RepayStatus=0)")->save($data);
		if($status === null){
			$this->ppdLog("mark bid User:$user_id bad user failed");
		}else if($status){
			$this->ppdLog("mark  bid User:$user_id bad user count: $status");
		}
		$lpr = M("lpr");
		$status = $lpr->where("UserId = '{$user_id}' AND RepayStatus = 0")->save($data);
		if($status === null){
			$this->ppdLog("mark lpr User:$user_id bad user failed");
		}else if($status){
			$this->ppdLog("mark  lpr User:$user_id bad user count: $status");
		}
		
	}

}
