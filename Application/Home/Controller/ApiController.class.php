<?php
namespace Home\Controller;
use Think\Controller;
class ApiController extends BaseController {
    private $cip;//client ip;

    public function _initialize(){
        $this->cip=$_SERVER['REMOTE_ADDR'];
        //if($this->cip!="180.171.87.31")
            //die("invalid");

    }
    public function apiGetServerTime()
    {
        $data[time]=time();
        echo  json_encode($data);
    }
    public function apiGetUserInfo()
    {
        $index=$_POST['index'];
        $time=$_POST['time'];
        $sign=$_POST['sign'];
        $signdata=$index.$time;
        $res['status']=0;
        $userinfo=array();
        if($this->verify($signdata, $sign)&&time()-$time<60)
        {
            $timestamp_later=date("Y-m-d H:i:s",time()+3600);
            $m=M("user");
            $data=$m->join('left join ppd_user_global_setting on ppd_user.UserId = ppd_user_global_setting.UserId')->where("ATExpireDate>'{$timestamp_later}' AND Score>2000")->field('ppd_user.UserId,UserName,AccessToken,UserBalance,Score,MinBalance,MinRate,MinMonth,MaxMonth')->cache(true,1)->select();
            if($data===false){
                $this->ppdLog("API GET USER INFO ERROR",2);
                $res['status']=-1;
                $res['msg']="INTERNAL ERROR";
            }else{
                if($data){
                    foreach($data as $user)
                    {
                        if($user['UserId']%2==$index){
                            if ($user['MinBalance'] == null || ($user['MinBalance']  < $user['UserBalance']))
                                array_push($userinfo, $user);
                            else{
                                $this->ppdLog($user['UserId'] . "skipped due to MinBalance not fit!" . json_encode($user));
                            }
                        }
                    }
                }
                $res['status']=0;
                $res['msg']='OK';
                
            }
        }else{
            $res['status']=400;
            $res['msg']="INVALID SIGN";
        }
        $res['count']=count($userinfo);
        $res['userinfo']=$userinfo;
        echo json_encode($res);
    }
    public function apiGetVipUserId()
    {
    	$index=$_POST['index'];
    	$time=$_POST['time'];
    	$sign=$_POST['sign'];
    	$signdata=$index.$time;
    	$res['status']=-1;
    	$vip=array();
    	if($this->verify($signdata, $sign)&&time()-$time<60)
    	{
    		$res['status']=0;
    		$vip = $this->vip;
    	}
    	$res['count']=count($vip);
    	$res['vip']=$vip;
    	echo json_encode($res);
    }
    public function apiGetUserSetting()
    {
        $index=$_POST['index'];
        $time=$_POST['time'];
        $sign=$_POST['sign'];
        $signdata=$index.$time;
        $res['status']=0;
        $setting=array();
        if($this->verify($signdata, $sign)&&time()-$time<60)
        {
            $m=M("strategy_setting");
            $data=$m->where("ApplyStatus > 0")->field('UserId,StrategyId,BidAmount')->cache(true,300)->select();
            if($data===false){
                $this->ppdLog("API GET USER STRATEGY SETTING ERROR",2);
                $res['status']=-1;
                $res['msg']="INTERNAL ERROR";
            }else{
                if($data){
                    foreach($data as $user)
                    {
                        //if($user['UserId']%2==$index){
                            array_push($setting, $user);
                        //}
                    }
                }
                $res['status']=0;
                $res['msg']='OK';
    
            }
        }else{
            $res['status']=400;
            $res['msg']="INVALID SIGN";
        }
        $res['count']=count($setting);
        $res['setting']=$setting;
        echo json_encode($res);
    }
    public function apiGetSysSafeStrategy()
    {
        $index=$_POST['index'];
        $time=$_POST['time'];
        $sign=$_POST['sign'];
        $signdata=$index.$time;
        $res['status']=0;
        $sys=array();
        if($this->verify($signdata, $sign)&&time()-$time<60)
        {
            // Rate>=RateA && Rate<RateB && Months>=MonthsA&&Months<MonthsB && CreditCode='AA'
//             $data['1']=array("RateA"=>11,"RateB"=>12,"MonthsA"=>1,"MonthsB"=>2);
//             $data['2']=array("RateA"=>11,"RateB"=>12,"MonthsA"=>3,"MonthsB"=>4);
//             $data['3']=array("RateA"=>12,"RateB"=>12.5,"MonthsA"=>1,"MonthsB"=>10);
//             $data['4']=array("RateA"=>12.5,"RateB"=>13,"MonthsA"=>1,"MonthsB"=>19);
//             $data['5']=array("RateA"=>13,"RateB"=>15,"MonthsA"=>1,"MonthsB"=>25);
//             $data['6']=array("RateA"=>15,"RateB"=>25,"MonthsA"=>1,"MonthsB"=>37);
//             $data['7']=array("RateA"=>14,"RateB"=>15,"MonthsA"=>24,"MonthsB"=>37);
            $data['1000']=array("RateA"=>9,"RateB"=>9.5,"MonthsA"=>1,"MonthsB"=>37);
            $data['1001']=array("RateA"=>9.5,"RateB"=>10,"MonthsA"=>1,"MonthsB"=>37);
            $data['1002']=array("RateA"=>10,"RateB"=>10.5,"MonthsA"=>1,"MonthsB"=>37);
            $data['1003']=array("RateA"=>10.5,"RateB"=>11,"MonthsA"=>1,"MonthsB"=>37);
            $data['1004']=array("RateA"=>11,"RateB"=>11.5,"MonthsA"=>1,"MonthsB"=>37);
            $data['1006']=array("RateA"=>12,"RateB"=>12.5,"MonthsA"=>1,"MonthsB"=>37);
            $data['1007']=array("RateA"=>12.5,"RateB"=>13,"MonthsA"=>1,"MonthsB"=>37);
            $data['1008']=array("RateA"=>13,"RateB"=>13.5,"MonthsA"=>1,"MonthsB"=>37);
            $data['1010']=array("RateA"=>14,"RateB"=>36,"MonthsA"=>1,"MonthsB"=>37);
            $data['1021']=array("RateA"=>8.5,"RateB"=>9,"MonthsA"=>1,"MonthsB"=>37);
            $data['1022']=array("RateA"=>8,"RateB"=>8.5,"MonthsA"=>1,"MonthsB"=>37);
            $res['status']=0;
            $res['msg']='OK';
            $sys=$data;
        }else{
            $res['status']=400;
            $res['msg']="INVALID SIGN";
        }
        $res['count']=count($sys);
        $res['sys']=$sys;
        echo json_encode($res);
    }
    
    public function apiGetSysCreditStrategy()
    {
        $index=$_POST['index'];
        $time=$_POST['time'];
        $sign=$_POST['sign'];
        $signdata=$index.$time;
        $res['status']=0;
        $sys=array();
        if($this->verify($signdata, $sign)&&time()-$time<60)
        {
            $m=M("sys_strategy");
            $data=$m->where("Status ='0'")->cache(true, 300)->select();
            if($data===false){
                $this->ppdLog("API GET USER DIY STRATEGY SETTING ERROR",2);
                $res['status']=-1;
                $res['msg']="INTERNAL ERROR";
            }else{
                $res['status']=0;
                $res['msg']='OK';
                $sys=$data;
            }
        }else{
            $res['status']=400;
            $res['msg']="INVALID SIGN";
        }
        $res['count']=count($sys);
        $res['sys']=$sys;
        echo json_encode($res);
    }
    
    public function apiGetDiyStrategy()
    {
        $index=$_POST['index'];
        $time=$_POST['time'];
        $sign=$_POST['sign'];
        $signdata=$index.$time;
        $res['status']=0;
        $diy=array();
        if($this->verify($signdata, $sign)&&time()-$time<60)
        {
            $m=M("personal_strategy");
            $data=$m->where("ApplyStatus > '0' and Status='0'")->cache(true, 300)->select();
            if($data===false){
                $this->ppdLog("API GET USER DIY STRATEGY SETTING ERROR",2);
                $res['status']=-1;
                $res['msg']="INTERNAL ERROR";
            }else{
                $res['status']=0;
                $res['msg']='OK';
                $diy=$data;
            }
        }else{
            $res['status']=400;
            $res['msg']="INVALID SIGN";
        }
        $res['count']=count($diy);
        $res['diy']=$diy;
        echo json_encode($res);
    }
    
    public function apiSendBidRecord()
    {
        $count=$_POST['count'];
        $time=$_POST['time'];
        $sign=$_POST['sign'];
        $signdata=$count.$time;
        $res['status']=0;
        $records=$_POST['BidRecord'];
        if($this->verify($signdata, $sign)&&time()-$time<60 && $count>0 && count($records)==$count)
        {
            foreach ($records as $record )
            {
                if(isset($record['UserId']) && isset($record['StrategyId']) && isset($record['ListingId']) && isset($record['BidAmount']))
                {
                    $this->biddingRecord($record['UserId'], $record['ListingId'], $record['StrategyId'], $record['BidAmount'], $time, $record['appIndex']);
                }
            }
            $res['status']=0;
            $res['msg']="OK";
                
        }else{
            $res['status']=400;
            $res['msg']="INVALID SIGN OR PARAM";
        }
        echo json_encode($res);
    }

    private function biddingRecord($user_id,$list_id,$strategyid, $bidamount,$time, $appIndex = 0)
    {
        //$this->addDiyDailyAmount($strategyid, $user_id, $bidamount, $time);
        $this->ppdLog("AppIndex:$appIndex API BID Record: USER:$user_id,LIST_ID:$list_id,STRATEGY_ID:$strategyid; TIME:" . date("Y-m-d H:i:s",$time));
        $r=M("bid");
        $data['UserId']=$user_id;
        $data['ListingId']=$list_id;
        $data['StrategyId']=$strategyid;
        $data['BidAmount']=$bidamount;
        $data['BidTime']=date("Y-m-d H:i:s",$time);
        $data['BidSN']="$user_id$list_id$strategyid";
        $data['AppIndex']=$appIndex;
        $find=$r->where("UserId = '{$user_id}' and ListingId = '{$list_id}'")->find();
        if($find){
            $this->ppdLog("bidrecord-repeated; user,$user_id,list_id:$list_id,strategyid:$strategyid;",1);
            return;
        }
        if($strategyid >= 1000 && $strategyid < 9999){
		    $bidcost=$bidamount*$this->cost_rate;
        }else if($strategyid >= 100000){
		    $bidcost=$bidamount*$this->cost_rate;
	    }else{
		    $bidcost=$bidamount*$this->cost_rate_sys;
        }
        $this->ppdLog("bid cost $bidcost");
        $data['BidCost']=$bidcost;
            
        if(!$r->add($data))
            $this->ppdLog("biddingRecord add data failed!\n last database err is :" . $r->getDbError(),4);
        if($bidcost>0){
            $user=M("User");
            $status=$user->where("UserId='{$user_id}'")->setDec("Score",$bidcost);
            if($status===false){
                $this->ppdLog("biddingRecord Charge failed  \ndata:". json_encode($data) . "\nlast database err is :" . $user->getDbError(),3);
            }
        }
        //maybe need AND PAY DEAL;
    }
    
    public function testApi($api="apigetuserinfo")
    {
        echo "testapi</br>";
        $post_data['index']=1;
        $post_data['time']=time();
        $signdata=$post_data['index'].$post_data['time'];
        $post_data['sign']=$this->sign($signdata);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://www.p2phunter.cn/home/api/" . $api);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (windows NT 6.1) Applewebkit/537.17");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $data = curl_exec($ch);
        dump($data);
        curl_close($ch);
        echo "curlproxy task started!". time() . "</br>";
    }
    
}
