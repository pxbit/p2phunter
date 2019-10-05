<?php
namespace Home\Controller;
use Think\Controller;
use \Curl\Curl;
use PhpCollection\Sequence;
use Phpfastcache\CacheManager;

use Exception as BaseException;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

use Behat\Mink\Mink,
    Behat\Mink\Session,
    Behat\Mink\Driver\GoutteDriver,
    Behat\Mink\Driver\Goutte\Client as GoutteClient;

use Statsh, Stash\Pool;
use Pinq\ITraversable, Pinq\Traversable;

class StrategyController extends BaseController {


    public function _initialize(){
        if(!$this->isStatisticJustUpdated())
            $this->strategyStatistic();
    }
    public function index($mobile=0){
      $this->setAccessType();

      if($this->isUserLogin()||$this->isCookieValid()){
        if (session('accessType') == 2){
          /* 微信访问直接跳转到移动页面 */
          $this->assign("mbl", 1);

          /* 移动端信息也页面一起载入 */
          $info = $this->fetchUserStrategyInfo();
          $applied = $this->getStrategyAppliedStatus($info);

          $this->assign("strategyList", $info);
          $this->assign("applied", $applied);
          $this->display("default/strategy_m");
        } else {
          if ($mobile == 0){
            $this->assign("mbl", 0);
            $this->display("default/strategy");
          } else {
            $this->assign("mbl", 1);

            /* 移动端信息也页面一起载入 */
            $info = $this->fetchUserStrategyInfo();
            $applied = $this->getStrategyAppliedStatus($info);

            $this->assign("strategyList", $info);
            $this->assign("applied", $applied);
            $this->display("default/strategy_m");
          }
        }
      }
      else {
        $this->displayPage($mobile, "default/login", "default/login_m");
      }
    }

    public function stats($mobile=0)
    {
      $this->setAccessType();

      if($this->isUserLogin()||$this->isCookieValid()){
        $this->displayPage($mobile, "default/stats", "default/stats_m");
      }else{
       $this->displayPage($mobile, "default/login", "default/login_m");
      }
    }

    public function getSysStrategyStats()
    {
        $cache="sysStats";
        $res=$this->cacheGet($cache);
        echo json_encode($res);
    }

    public function getSysStatsFromRemote(){
      $curl = new Curl();

      /* 模拟登录获取cookie */
      $curl->post('http://p2phunter.cn/home/remote/login', array(
        'code' => 'p2phunter_001_code',
      ));

      if ($curl->error) {
        echo 'Error: ' . $curl->errorCode . ': ' . $curl->errorMessage . "\n";
      } else {
        /* 设置cookies 数组 */
        $cookies = $curl->getResponseCookies();
        $curl->setCookies($cookies);

        /* 获取数据 */
        $data = $curl->get('http://p2phunter.cn/home/strategy/getSysStrategyStats');
        $data = json_decode($data, true);

        $cache = "sysStats";
    		$this->cacheSave($cache, $data);
      }
    }

    private function updateBidRate($StrategyId,$rate){
    	$strategy = M("Strategy");
    	$data['BidRate'] = $rate;
    	$status = $strategy->where("StrategyId = '{$StrategyId}'")->save($data);
    	if($status === false){
    		$this->ppdLog("UPDATE BID RATE ERROR!",2);
    	}
    }
    
    public function saveSysStrategyStats()
    {
    	set_time_limit(0);
    	$cache="sysStats";
    	$res=$this->cacheGet($cache);
    	$strategy=M("Strategy");
    	$data=$strategy->where("StrategyId >=10 and StrategyId <1000 and status = 0 ")->field("StrategyId,Name,BidRate,DelayRate,ExpectRate")->order('StrategyId asc')->cache(true,120)->select();
    	if($data){
    		$lpr=M('lpr');
    		$day7ago=date("Y-m-d H:i:s",time()-3600*24*7);
    		$day30ago=date("Y-m-d H:i:s",time()-3600*24*30);
    		$day90ago=date("Y-m-d H:i:s",time()-3600*24*90);
    		foreach ($data as $id=>$s)
    		{
    			$strategy_id=$s['StrategyId'];
    			$first_delay30=$lpr->where("StrategyId='{$strategy_id}' and OrderId = 1 and OverdueDays>30 and RepayStatus=0")->count('distinct(ListingId)');
    			$first_all30=$lpr->where("StrategyId='{$strategy_id}' and OrderId = 1 and RepayStatus >=0 and (DueDate<'{$day30ago}' or (RepayDate<'{$day30ago}' and RepayDate>'1900-01-01 00:00:00'))")->count('distinct(ListingId)');
    			$second_delay30=$lpr->where("StrategyId='{$strategy_id}' and OrderId = 2 and OverdueDays>30 and RepayStatus=0")->count('distinct(ListingId)');
    			$second_all30=$lpr->where("StrategyId='{$strategy_id}' and OrderId = 2 and RepayStatus >=0 and (DueDate<'{$day30ago}' or (RepayDate<'{$day30ago}' and RepayDate>'1900-01-01 00:00:00'))")->count('distinct(ListingId)');
    			$third_delay30=$lpr->where("StrategyId='{$strategy_id}' and OrderId = 3 and OverdueDays>30 and RepayStatus=0")->count('distinct(ListingId)');
    			$third_all30=$lpr->where("StrategyId='{$strategy_id}' and OrderId = 3 and RepayStatus >=0 and (DueDate<'{$day30ago}' or (RepayDate<'{$day30ago}' and RepayDate>'1900-01-01 00:00:00'))")->count('distinct(ListingId)');
				
    			#30日逾期率 90日逾期率，用于和策略市场比较
    			$total_delay30=$lpr->where("StrategyId='{$strategy_id}' and  OverdueDays>30 and RepayStatus=0")->sum('OwingPrincipal');
    			$total_repay30=$lpr->where("StrategyId='{$strategy_id}'  and RepayStatus >0 and DueDate<'{$day30ago}'")->sum('RepayPrincipal');
    			$total_delay90=$lpr->where("StrategyId='{$strategy_id}' and  OverdueDays>90 and RepayStatus=0")->sum('OwingPrincipal');
    			$total_repay90=$lpr->where("StrategyId='{$strategy_id}'  and RepayStatus >0 and DueDate<'{$day90ago}'")->sum('RepayPrincipal');
    			
    			# 平均投资期限与平均投资利率：
    			$total_interest=$lpr->where("StrategyId='{$strategy_id}' ")->sum('RepayInterest + OwingInterest');
    			$total_principal=$lpr->where("StrategyId='{$strategy_id}' ")->sum('RepayPrincipal + OwingPrincipal');
    			$sum_order=$lpr->where("StrategyId='{$strategy_id}' ")->sum('OrderId');
    			$count_record = $lpr->where("StrategyId='{$strategy_id}' ")->count('UserId');
    			$avg_month = (($sum_order/($count_record + 0.00001))*2-1)*1.02;//1.02 为修正系数，因为有提前还款的。
 
    			$data[$id]['FD30']=$first_delay30?$first_delay30:0;
    			$data[$id]['FA30']=$first_all30?$first_all30:0;
    			$data[$id]['SD30']=$second_delay30?$second_delay30:0;
    			$data[$id]['SA30']=$second_all30?$second_all30:0;
    			$data[$id]['TD30']=$third_delay30?$third_delay30:0;
    			$data[$id]['TA30']=$third_all30?$third_all30:0;
    			$data[$id]['D30']=round($total_delay30/($total_delay30 + $total_repay30 + 0.00001),3);
    			$data[$id]['D90']=round($total_delay90/($total_delay90 + $total_repay90 + 0.00001),3);
    			$data[$id]['AVGM']=round($avg_month,1);
    			$rate = $total_interest*12/($total_principal*$avg_month/2 + 0.00001);
    			$data[$id]['AVGR']=round((pow($rate + 1, 0.083333) - 1)*12,3);
    			if($s['BidRate'] != $data[$id]['AVGR']*100){
    				$this->updateBidRate($strategy_id, $data[$id]['AVGR']*100);
    			}
    		}
    		$res['status']=0;
    		$res['stats']=$data;
    		$res['time']=time();
    		$this->cacheSave($cache, $res);
    	}
    	else{
    		$res['status']=-1;
    		$res['ErrMsg']="No Strategy Found!";
    	}
    	$infodata=json_encode($res);
    	echo $infodata;
    }
    
    
    public function diy($stid=""){
        /*$si is the StrategyId, It must be zero or exsit si of the current user*/
        if($stid=="")
            die("你从哪里来？到哪里去？能找到这个页面，证明你不是凡人！ 不过，你还是老老实实点击菜单操作吧");
        $stid=intval($stid);
        $user_id=$this->isCookieValid();
        if(!$user_id)
            $user_id=$this->isUserLogin();
        if($user_id){
            if($this->isStidLegal($user_id,$stid)){
                $this->display("default/diy");
                session('stid',$stid,3600);
            }else{
                $this->redirect('Strategy/index');
            }
        }
        else 
            $this->display("default/login");
    }
    public function isStidLegal($user_id,$stid)
    {
        $m=M("personal_strategy");
        if($user_id==2){
            return true;
        }else if($stid>=100000){
            $res=$m->where("UserId='{$user_id}' AND StrategyId='{$stid}'")->find();
            return !!$res;
        }else if ($stid==0){
            $res=$m->where("UserId='{$user_id}'")->count();
            return !($res===false||$res>=$this->max_diy_count);
        }else 
            return false;
    }
    public function getdiyStrategySetting()
    {
        $user_id=$this->isCookieValid();
        if(!$user_id)
            $user_id=$this->isUserLogin();
        $stid=session('stid');
        $result['status']=-1;
        if($user_id&&$stid){

            if($user_id==2){
                $m=M("sys_strategy");
                $res=$m->where("UserId='10' AND StrategyId='{$stid}'")->find();
            }else{
                $m=M("personal_strategy");
                $res=$m->where("UserId='{$user_id}' AND StrategyId='{$stid}'")->find();
            }
            if($res){
                foreach ($res as $id=>$item){
                    if($item==-1)
                    $res[$id]="";
                }
                $result['status']=0;
                $result['data']=$res;
            }else{
                $this->ppdLog("UserId='{$user_id}' AND StrategyId='{$stid} NOT FOUND",3);
            }
        }
        echo json_encode($result);
        
    }
    /*
    `Credit` int(11) DEFAULT '127',
    `Degree`  int(11) DEFAULT '63',
    `StudyStyle`  int(11) DEFAULT '63',
    `School`  int(11) DEFAULT '15',
    `Gender` tinyint(1) DEFAULT '3',
    `Age`  int(11) DEFAULT '31',
    -------------------------------
    `CertificateValidate` tinyint(1) DEFAULT '0',
    `CreditValidate` tinyint(1) DEFAULT '0',
    `EducateValidate` tinyint(1) DEFAULT '0',
    `VideoValidate` tinyint(1) DEFAULT '0',
    `PhoneValidate` tinyint(1) DEFAULT '0',
    `NciicIdentityCheck` tinyint(1) DEFAULT '0',
     */
    public function diySubmit(){
        $user_id=$this->isCookieValid();
        if(!$user_id)
            $user_id=$this->isUserLogin();
        $stid=session('stid');
        $result['status']=0;
        if($user_id){
            if($this->isStidLegal($user_id, $stid)){
                $arr=$_POST;
                foreach ($arr as $key=>$value){
                    if($value=="")
                        $arr[$key]=-1;
                }
                $arr['ConditionMask']=$arr['Credit']|$arr['Degree']<<7|$arr['StudyStyle']<<13|$arr['School']<<19|$arr['Gender']<<23|$arr['Age']<<25;
                $arr['ValidateCode']=$arr['CertificateValidate']|$arr['CreditValidate']<<1|$arr['PhoneValidate']<<4|$arr['NciicIdentityCheck']<<5;
                $arr['ValidateCodeFalse']=$arr['CertificateValidateFalse']|$arr['CreditValidateFalse']<<1|$arr['PhoneValidateFalse']<<4|$arr['NciicIdentityCheckFalse']<<5;
                if($user_id==2){
                    $arr['UserId']=10;
                    if($stid==0)
                        $arr['StrategyLevel']=0;
                    else
                        $arr['StrategyId']=$stid;
                    $m=M("sys_strategy");
                }else{
                    $arr['UserId']=$user_id;
                    if($stid==0)
                        $arr['StrategyLevel']=$this->getStrategyPriority($user_id);
                    else
                        $arr['StrategyId']=$stid;
                    $m=M("personal_strategy");
                }

                $arr['UpdateTime']=date("Y-m-d H:i:s",time());
                $status=$m->add($arr,array(),true);
                if($status===false){
                    $this->ppdLog("diySubmit add Strategy ERROR" . json_encode($m->getDbError()),3);
                    $result['status']=-1;
                    $result['message']="服务器忙，请稍后再试！";
                }else{
                    $result['status']=0;
                }
            }else{
                $result['status']=-1;
                $result['message']="对不起!策略数已超限，您不能再添加策略";
            }
            
        }else{
            $result['status']=-1;
            $result['message']="对不起!登陆已超时，请刷新后重新登陆！";
        }
        echo json_encode($result);
            
    }
    
    public function diyMatchTest(){
        $user_id=$this->isCookieValid();
        if(!$user_id)
            $user_id=$this->isUserLogin();
        $stid=session('stid');
        $result['status']=0;
        if($user_id){
            $lasttested=session('lasttested');
            if(isset($lasttested)&&time()-$lasttested<5){
                $result['status']=-1;
                $result['message']="您测试太频繁了！30s后再来测试";
                echo json_encode($result);
                return;
            }else{
                session('lasttested',time(),5);
            }
                
            if($this->isStidLegal($user_id, $stid)){
                $diy=$_POST;
                foreach ($diy as $key=>$value){
                    if($value=="")
                        $diy[$key]=-1;
                }
                $diy['ConditionMask']=$diy['Credit']|$diy['Degree']<<7|$diy['StudyStyle']<<13|$diy['School']<<19|$diy['Gender']<<23|$diy['Age']<<25;
                $diy['ValidateCode']=$diy['CertificateValidate']|$diy['CreditValidate']<<1|$diy['PhoneValidate']<<4|$diy['NciicIdentityCheck']<<5;
                $diy['ValidateCodeFalse']=$diy['CertificateValidateFalse']|$diy['CreditValidateFalse']<<1|$diy['PhoneValidateFalse']<<4|$diy['NciicIdentityCheckFalse']<<5;
                $diy['UserId']=$user_id;
                $autobid=A("Autobid");
                $loans=$autobid->getLatestHistoryLoanDetail();
                $matched_loans=$autobid->getMatchedloans($loans,$diy);
                if(empty($matched_loans)){
                    $result['status']=-1;
                    $result['message']="很遗憾，你的策略在最近出现的5000个标中无匹配标";
                }else{
                    
                    $result['summary']="策略匹配率" . count($matched_loans) . "/" . count($loans) . "下面是" . (count($matched_loans)>20?"部分":"全部") . "样例。";
                    if(count($matched_loans)>20)
                        $result['loans']=array_slice($matched_loans,0,20);
                    else 
                        $result['loans']=$matched_loans;
                }
                
            }else{
                $result['status']=-1;
                $result['message']="对不起!你策略超限，本操作无法完成";
            }
                
        }else{
            $result['status']=-1;
            $result['message']="对不起!登陆已超时，请刷新后重新登陆！";
        }
        echo json_encode($result);
            
    }
    public function getStrategyPriority($user_id)
    {
        $m=M("personal_strategy");
        $count=$m->where("UserId='{$user_id}'")->count();
        if($count===false){
            $this->ppdLog("getStrategyPriority DB ERROR");
            return -1;
        }else if($count>=3){
            return -1;
        }else if($count>=1){
            return 1;
        }else if($count==0){
            return 0;
        }else{
            $this->ppdLog("getStrategyPriority DB UNKNOWN ERROR" . json_encode($m->getDbError()),3);
            RETURN -1;
        }
    }
    public function getDiyStrategyList()
    {
        $user_id=$this->isCookieValid();
        if(!$user_id)
            $this->isUserLogin();
        $res['status']=0;
        $res['data']=array();
        if($user_id){

            if($user_id==2){
                $m=M("sys_strategy");
                $data=$m->where("UserId='10'")->select();
            }else{
                $m=M("personal_strategy");
                $data=$m->where("UserId='{$user_id}'")->select();
            }
            if($data===false){
                $this->ppdLog("getDiyStrategyList ERROR" . json_encode($m->getDbError()),3);
                $res['status']=-1;
                $res['message']="服务器忙！请稍后再试！";
            }else if($data){
                $res['diylist']=$data;
            }
        }
        return $res;

    }
    public function getStrategyList($echo=1)
    {
            $strategy=M("Strategy");
            $data=$strategy->where("status>=0")
                           ->field("StrategyId,Name,Discription,BidRate,ExpectRate,DelayRate,K7")
                           ->order('StrategyId asc')
                           ->cache(true,120)
                           ->select();
            if($data){
                $res['status']=0;
                $res['unilist']=$data;
                $res['diy']=$this->getDiyStrategyList();
            }
            else{
                $res['status']=-1;
                $res['ErrMsg']="No Strategy Found!";
            }
            if ($echo == 1){
              $infodata=json_encode($res);
              echo $infodata;
            }

            return $res;
    }

    public function isStrategyExist($strategy_id)
    {
        $strategy_id=(int)$strategy_id;
        $strategy=M("strategy");
        if($strategy->where("strategyId='{$strategy_id}'")->find()){
            return true;
        }
        else {
            return false;
        }
    }
    public function getMatchList($strategy_id)//api
    {
        $strategy=M("bid");
        $data=$strategy->where("StrategyId='{$strategy_id}' AND UserId='10'")->order('UpdateTime desc')->limit(20)->cache(true,600)->select();
        if($data){
            $res['status']=0;
            $res['matchList']=$data;
        }else{
            $res['status']=-1;
            if($data===false)
                $this->ppdLog("getMatchList DB ERROR",3);
        }
        echo json_encode($res);
    }

    public function updateUserStrategySetting($strategy_id, $val, $switch)
    {
        //合法性检验
        $val=(int)$val;
        $userid=$this->isCookieValid();
        if(!$userid)
            $this->isUserLogin();
        if($strategy_id<100 && !$this->isStrategyExist($strategy_id))
        {
            $data['status']=-2;
            $data['ErrMsg']="INVALID STRATEGY ID:" . $strategy_id ;
        }else if( $val<0 || $val>500)
        {
            $data['status']=-2;
            $data['ErrMsg']="INVALID BID AMOUNT:" . $val;
        }else if($userid>0)//登录的用户
        {
            if($strategy_id>=100000)
                $strategy=M("personal_strategy");
            else
                $strategy=M("strategy_setting");
            $data['BidAmount']=$val;

            $data['ApplyStatus']= $switch;

            $condition['UserId']=$userid;
            $condition['StrategyId']=$strategy_id;
            if($strategy->where($condition)->find()){
                $status=$strategy->where("UserId='{$userid}' AND StrategyId='{$strategy_id}'")->save($data);
                if($status!==false)
                    $data['status']=0;
                else {
                    $data['status']=-1;
                    $data['ErrMsg']="UPDATE USER STRATEGY SETTING FAILED!";
                    $this->ppdLog($data['ErrMsg'] . "UserId:" .$userid . "StrategyId:" . $strategy_id);
                }
            }else{
                if($strategy_id>=100000){
                    $this->ppdLog("USER:{$userid},strategy_id:{$strategy_id} NOT FOUND",3);
                    $data['status']=-1;
                    $data['ErrMsg']="没找到您的策略，请联系客服咨询原因！";
                }else{
                    $condition['BidAmount']=$val;
                    $condition['ApplyStatus']= $switch;
                    $status=$strategy->add($condition);
                    if($status)
                        $data['status']=0;
                    else {
                        $data['status']=-1;
                        $data['ErrMsg']="ADD USER STRATEGY SETTING FAILED!";
                        $this->ppdLog($data['ErrMsg'] . "UserId:" .$userid . "StrategyId:" . $strategy_id);
                    }
                }   
            }
        }else {
            $data['status']=-3;
            $data['ErrMsg']="USER NOT LOGIN";
        }
        $infodata=json_encode($data);
        echo $infodata;
    }

    public function getUserStrategySetting($Strategy_id=-1)
    {
        $userid=$this->isCookieValid();
        if(!$userid)
            $userid=$this->isUserLogin();
        if( is_int($Strategy_id) && $userid ){
            $strategy=M("Strategy_setting");
            if($Strategy_id>0)
                $data=$strategy->where("UserId='{$userid}' AND StrategyId='{$Strategy_id}'")->field("StrategyId,BidAmount,ApplyStatus")->select();
            else
                $data=$strategy->where("UserId='{$userid}' AND BidAmount>=50")->field("StrategyId,BidAmount,ApplyStatus")->select();
            if($data){
                $data['status']=0;
            }
            else{
                $data['status']=-1;
                $data['ErrMsg']="No Effecttive Strategy Setting Found!";
            }
            $infodata=json_encode($data);
            echo $infodata;
        }
    }

    public function getUserAllStrategySetting(){
        $userid = $this->isCookieValid();
        if (!$userid)
            $userid=$this->isUserLogin();

        $data = [];
        if ($userid){
          $strategy = M("Strategy_setting");
          $data=$strategy->where("UserId='{$userid}'")
                         ->field("StrategyId, BidAmount, ApplyStatus")
                         ->select();
        }

        return $data;
    }

    public function updateUserGlobalSetting($minRate, $minMonth, $minBalance, $maxMonth)
    {
    	//合法性检验
    	$userid=$this->isCookieValid();
    	if(!$userid)
    		$userid = $this->isUserLogin();
    	if ($userid){
    	    $setting = array();
    	    $setting['MinRate'] = (float)$minRate;
    	    $setting['MinMonth'] = (int)$minMonth;
    	    $setting['MinBalance'] = (int)$minBalance;
    	    $setting['MaxMonth'] = (int)$maxMonth;
    	    $setting['UserId'] = $userid;
    	    $ugs = M("user_global_setting");
    	    $status = $ugs->add($setting, array(), true);
    	    if($status === false){
    	        $data['status'] = -2;
    	        $data['ErrMsg'] = "数据库错误，请联系管理员！";
    	    }else{
    	        $data['status'] = 0;
    	        $data['ErrMsg'] = "OK";
    	        $data['data'] = "起投利率:" . $setting['MinRate'] . " 保留金额:" . $setting['MinBalance'] . " 月份：" . $setting['MinMonth'] . "-" . $setting['MaxMonth'];
    	    }
    	}else {
    		$data['status']=-3;
    		$data['ErrMsg']="USER NOT LOGIN";
    	}
    	$infodata=json_encode($data);
    	echo $infodata;
    
    }
    
    public function getUserGlobalSetting()
    {
        $userid=$this->isCookieValid();
        if(!$userid)
        	$userid = $this->isUserLogin();
        if ($userid){
        	$ugs = M("user_global_setting");
        	$data = $ugs->where("UserId = '{$userid}'")->find();
        	if($data === false){
        		$data['status'] = -2;
        		$data['ErrMsg'] = "数据库错误，请联系管理员！";
        	}else{
        		$data['status'] = 0;
        		$data['ErrMsg'] = "OK";
        	}
        }else {
        	$data['status']=-3;
        	$data['ErrMsg']="USER NOT LOGIN";
        }
        $infodata=json_encode($data);
        echo $infodata;
        
    }
    
    public function isStatisticJustUpdated(){
        $strategy=M("strategy");
        $timestamp=date('Y-m-d H:i:s',time()-7200);
        $updateNum=$strategy->where("UpdateTime>'{$timestamp}'")->count();
        if($updateNum && $updateNum>5){
            return true;
        }else if($updateNum===false){
            $this->ppdLog("isStatisticJustUpdated Query ERROR:" , $strategy->getDbError());
        }
        return false;
        
    }
    
    public function strategyStatistic(){
        $strategy=M("strategy");
        $strategy_list=$strategy->where("status='0'")->select();
        if(!empty($strategy_list)){
            $date=date('Y-m-d H:i:s',time()-7*24*60*60);
            $now=date('Y-m-d H:i:s',time());
            $bid=M("bid");
            $clean=$bid->where("BidTime<'{$date}' AND UserId='10'")->delete();
            if($clean===false)
                $this->ppdLog("strategyStatistic: clean bid record ERROR:" . $bid->getDbError() . " last SQL:". $bid->getLastSql(),3);
            foreach ($strategy_list as $id=>$strategy_info){
                $strategy_id=$strategy_info['StrategyId'];
                $count=$bid->where("StrategyId='{$strategy_id}' AND BidTime >'{$date}'")->count('distinct(ListingId)');
                if($count===false){
                    $this->ppdLog("strategyStatistic:Strategy select ERROR:" . $bid->getDbError() . " last SQL:". $bid->getLastSql(),3);
                }else{
                    $strategy_info['K7']=$count;
                    $strategy_info['UpdateTime']=$now;
                    $status=$strategy->save($strategy_info);
                    if($status===false)
                        $this->ppdLog("strategyStatistic:Strategy save statistic ERROR:" . $bid->getDbError() . " last SQL:". $bid->getLastSql(),3);
                }
            }

        }else if($strategy_list===false){
            $this->ppdLog("strategyStatistic: get strategy ERROR,last error:" . $strategy->getDbError() . " last SQL:". $strategy->getLastSql(),3);
        }
    }

    public function fetchUserStrategyInfo(){
      $list    = $this->getStrategyList($echo=0);
      $sysList = $list['unilist'];
      $diyList = $list['diy']['diylist'];

      $info = [];
      foreach ($sysList as $rcd){
        $itm = $rcd;

        $id = $rcd['StrategyId'];
        $itm['id'] = $id;

        if($id < 10 || ($id >= 1000 && $id < 2000)){
          $itm['ctag'] ='陪';
        } else {
          $itm['ctag'] ='信';
        }

        $setting = $this->getUserAllStrategySetting();
        if (count($setting) > 0){
          foreach ($setting as $val){
            $bidAmount = intval($val['BidAmount']);
            if ($rcd['StrategyId'] == $val['StrategyId']){
              $bidAmount = $val['BidAmount'];
              $switch = $val['ApplyStatus' ];
              break;
            }
          }

          $itm['bidAmount'] = $bidAmount;
          $itm['switch'] = (1 == $switch)? true : false;
        }

        /* 格式化 */
        if ($itm['BidRate'] == -1){
          $itm['BidRate'] = '-';
        } else {
          $itm['BidRate'] = $itm['BidRate'] . '%';
        }

        if ($itm['ExpectRate'] == -1){
          $itm['ExpectRate'] = '-';
        } else {
          $itm['ExpectRate'] = $itm['ExpectRate'] . '%';
        }

        $info[] = $itm;
      }

      foreach ($diyList as $rcd){
        $itm['Name']        = $rcd['StrategyName'];
        $itm['StrategyId']  = $rcd['StrategyId'];
        $itm['id']          = $rcd['StrategyId'];
        $itm['bidAmount']   = $rcd['BidAmount'];
        $itm['Description'] = $rcd['Description'];
        $itm['ctag']        = '自';
        $itm['BidRate']     = '-';
        $itm['ExpectRate']  = '-';
        $itm['K7']          = '-';
        $itm['switch'] = (1 == $rcd['ApplyStatus'])? true : false;

        $info[] = $itm;
      }

      //$this->stashSet('info', $info);
      return $info;
    }

    function isAllStrategyApplyed($info){
      foreach ($info as $val){
        if (false == $val['switch']){
          return false;
        }
      }

      return true;
    }

    function getStrategyAppliedStatus($info){
      $d['cntTotal'] = count($info);
      $d['cntOn']    = 0;
      $d['cntOff']   = 0;

      foreach ($info as $val){
        if (true == $val['switch']){
          $d['cntOn']++;
        } else {
          $d['cntOff']++;
        }
      }

      /* 只要有一个没有使能 */
      if ($d['cntOff'] > 0)
        $d['isAllApplied'] = false;
      else
        $d['isAllApplied'] = true;

      return $d;
    }

    function ajaxGetStrategyAppliedInfo(){
      $info    = $this->fetchUserStrategyInfo();
      $applied = $this->getStrategyAppliedStatus($info);
      $info    = json_encode($applied);
      echo $info;
    }

    function ajaxGetStrategyStats(){
      $this->getSysStrategyStats();
    }

    function testApplyAllSwitch(){
      $info = $this->fetchUserStrategyInfo();
      $res =  $this->isAllStrategyApplyed($info);
      echo ($res==true)? "yes":"no";
    }

    function testPhpCollection(){
        $seq = new Sequence([0, 2, 3, 2]);
        $seq->get(2); // int(3)
        $seq->all(); // [0, 2, 3, 2]

        $seq->first(); // Some(0)
        $seq->last(); // Some(2)

        var_dump($seq->all());
    }

    function testPhpfastcache(){
        CacheManager::getInstance('files', $config);
        // An alternative exists:
        CacheManager::Files($config);

    }

    function testWo(){
        $whoops = new \Whoops\Run;
        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
        $whoops->register();

    }

    function testMink(){
        $startUrl = 'http://www.p2phunter.cn';

        // init Mink and register sessions
        $mink = new Mink(array(
            'goutte1' => new Session(new GoutteDriver(new GoutteClient())),
            'goutte2' => new Session(new GoutteDriver(new GoutteClient())),
            'custom'  => new Session(new MyCustomDriver($startUrl))
        ));

        // set the default session name
        $mink->setDefaultSessionName('goutte2');

        // visit a page
        $mink->getSession()->visit($startUrl);

        // call to getSession() without argument will always return a default session if has one (goutte2 here)
        $mink->getSession()->getPage()->findLink('Downloads')->click();
        echo $mink->getSession()->getPage()->getContent();

        // call to getSession() with argument will return session by its name
        $mink->getSession('custom')->getPage()->findLink('Downloads')->click();
        echo $mink->getSession('custom')->getPage()->getContent();

        // this all is done to make possible mixing sessions
        $mink->getSession('goutte1')->getPage()->findLink('Chat')->click();
        $mink->getSession('goutte2')->getPage()->findLink('Chat')->click();
    }

    function testStash(){
        $driver = new Stash\Driver\FileSystem();

        $pool = new Pool($driver);
        $item = $pool->getItem('realtime');

        $data = array('abc','a','cdef');
        $pool->save($item->set($data));

        var_dump($item->get());
    }

    function testPinq(){
        $data = $this->stashGet('info');
        $strings = Traversable::from($data);

        foreach($strings->where(function ($rcd) { return $rcd['BidRate']!='-'; }) as $rcd) {
            gdump($rcd);
        }

    }


}

