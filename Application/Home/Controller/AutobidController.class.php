<?php
namespace Home\Controller;
use Think\Cache;
use Common\Util\Pedis as Pedis;
use Think\Controller;
class AutobidController extends BaseController {

    private $bid_queue=array();
    private $wait_queue=array();
    private $user_balance=array();
    private $even_queue=array();
    private $last_time_get_loan_list;
    private $last_time_scan_strategy_setting;
    private $last_time_check_accesstoken;
    private $last_time_scan_event;
    private $last_time_check_bid_queue;
    private $last_time_check_wait_queue;
    private $bid_count = 0;
    private $time_diff = 0;
    private $sync_server="www.ppdai.com";
    //private $sync_server="gw.open.ppdai.com";
    private $latest_list_id_queue=array();
    private $loan_detail_list=array();
    private $detail_request_list=array();
    private $loan_request_list=array();
    private $bid_request_list=array();
    private $safe_loan_cache="safe_loan_cache";
    private $credit_loan_cache="credit_loan_cache";
    
    private $BiddingRecordKey = "BiddingRecord";// 投标后带查询队列 键名
    
    private $bidAppIndex = 8;
    private $bidAppPrivateKey = null;
    private $bidAppId = null;
    
    public function _initialize(){
        $m = M("appid");
        $data = $m->where("AppIndex = '{$this->bidAppIndex}'")->find();
        if($data){
            $this->bidAppPrivateKey = $data['PrivateKey'];
            $this->bidAppId = $data['AppId'];
        }else{
            $this->ppdLog("FatalError .. App Key Not Found\n");
        }
    }
    
    public function index(){
    }

    public function stop()
    {
        $this->stopBidding();
        $this->stopBidding();
        $this->stopDownload();
        $this->stopDownload();
        $this->stopNeaten();
        $this->stopNeaten();
        $this->stopDiyBidding();
        $this->stopDiyBidding();
    }
    public function showQueue()
    {
        $this->updatecommand('showqueue');
        $this->updatecommand('showqueue');
    }
    
    public function storeControlStatus($data,$name='AutoBidStatus') {
        $m=M("setting");
        $find=$m->where("Name='{$name}'")->find();
        if($find){
            $data['UpdateTime']=date("Y-m-d H:i:s",time());
            $data=$m->where("Name='{$name}'")->save($data);
            if($data===false)
                $this->ppdLog("storeControlStatus:  {$name} update err",4);
        }else {
            $data['Name']=$name;
            $data=$m->add($data);
            if($data==false)
                $this->ppdLog("storeControlStatus:  {$name} add err",4);
        }
        
    }
    public function getControlStatus($name='AutoBidStatus') {
        $m=M("setting");
        $data=$m->where("Name='{$name}'")->cache(true,10)->find();
        if($data)
            return $data['val'];
        else{
            $this->ppdLog("getControlStatus_{$name}:  err",2);
            return null;
        }
    }
    
/* check if process is alive. check every 2 seconds.
 * return   true represent alive
 *          false represent dead
 *          null represent error. progress will exit.
 */
    public function isAlive($name='AutoBidStatus') {
        static $last_time_check=0;
        $timestamp=time();
        if($timestamp-$last_time_check>2){
            $last_time_check=$timestamp;
            $m=M("setting");
            $data=$m->where("Name='{$name}'")->find();
            if($data){
                if($timestamp-strtotime($data['UpdateTime'])>15)
                    return false;
                else return true;
            }
            else{
                $this->ppdLog("getHeartBeat_{$name}:  err",2);
                return null;
            }
        }else{
            return true;
        }
    }
    public function updateHeartBeat($name='AutoBidStatus') {
        static $last_time_update=0;
        if(time()-$last_time_update>5){
            $last_time_update=time();
            $m=M("setting");
            $find=$m->where("Name='{$name}'")->find();
            if($find)
            {   
                $data['UpdateTime']=date("Y-m-d H:i:s",time());
                $r=$m->where("Name='{$name}'")->save($data);
                if($r===false){
                    $this->ppdLog("updateHeartBeat{$name}:  err",2);
                }
            }
        }
    }
    public function stopDownload()
    {
        $this->updatecommand('stop',"AutoBidStatus_1");
        $this->updatecommand('stop',"AutoBidStatus_0");
    }
    public function stopBidding()
    {
        $this->updatecommand('stop',"AutoDownloadStatus_1");
        $this->updatecommand('stop',"AutoDownloadStatus_2");
        $this->updatecommand('stop',"AutoDownloadStatus_3");
    }
    public function stopDiyBidding()
    {
        //startDiyBidding
        $this->updatecommand('stop',"startDiyBidding0");
        $this->updatecommand('stop',"startDiyBidding1");
    }
    public function stopNeaten()
    {
        $this->updatecommand('stop',"NeatenStatus");
    }
    public function updatecommand($command,$name='AutoBidStatus_2') {
        $m=M("setting");
        $find=$m->where("Name='{$name}'")->find();
        if($find)
        {
            $data['val']=$command;
            $r=$m->where("Name='{$name}'")->save($data);
            if($r===false){
                $this->ppdLog("updatecommand:  err",2);
            }
        }
    }
    private function isUserOnline()
    {
        return true;
        //todo
    }

    private function isStrategyEnable($user_id){
        return true;
        //todo
        $ss=M("strategy_setting");
        $find=$ss->where("UserId='{$user_id}' and BidAmount>=50")->find();
        if($find){
            return true;
        }else if($find===false){
            $this->ppdLog("DB ERROR WHEN TRY TO FIND USER STRATEGY SETTING");
            return true;
        }else {
            $diy=M("personal_strategy");
            $find=$diy->where("UserId='{$user_id}' and BidAmount>=50")->find();
            if($find){
                return true;
            }else if ($find===false){
                $this->ppdLog("DB ERROR WHEN TRY TO FIND USER DIY STRATEGY SETTING");
                return true;
            }else{
                return false;
            }
        }
            
    }
    
    public function updateAllUserBalance($interval=60)
    {
        $m=M('user');
        $timestamp=date("Y-m-d H:i:s",time()-$interval);
        $timestamp_later=date("Y-m-d H:i:s",time()+600);
        $user_list=$m->where("(UBUpdateTime<'{$timestamp}' or UBUpdateTime is null) and RTExpireDate>'{$timestamp_later}' and Score >2000")->order("UBUpdateTime asc")->limit(10)->select();
        if($user_list===false){
            $this->ppdLog("updateAllUserBalance SELECT ERROR:" . json_encode($m->getDbError()),3);
        }else{
            foreach ($user_list as $user){
                if(!$this->isStrategyEnable($user['UserId']))
                {   if(!$this->isUserOnline()){
                        $this->ppdLog("USER :" . $user['UserId'] . "CLOSED ALL STRATEGY AND IS CLEARED!",1);
                        $this->disactiveUser($user['UserId']);
                    }
                }else{
                    $balance=$this->getBalance($user['AccessToken']);
                    if($balance>=0)
                    {
                        $user['UBUpdateTime']=date("Y-m-d H:i:s",time());
                        $user['UserBalance']=$balance;
    
                    }else if($balance==-400) {
                            $status=$this->userRefreshToken($user['UserId']);
                            if($status){
                                $this->ppdLog("updateAllUserBalance CLEAR USER:" . $user['UserId'] . "status:" . $status ,1);
                                $user['UBUpdateTime']=date("Y-m-d H:i:s",time()+600);
                                if($status!=-2){
                                    $user['ATExpireDate']=0;
                                    $user['RTExpireDate']=0;
                                    $this->ppdLog("updateAllUserBalance: USER:" . $user['UserId'] . "CLEARD!");
                                }
                            }
                            else {
                                continue;
                            }
                    }else{
                        $this->ppdLog("updateAllUserBalance: getBalance ERROR CODE:{$balance}",2);
                        $user['UBUpdateTime']=date("Y-m-d H:i:s",time()+300);
                    }
                    $status=$m->save($user);
                    if($status===false){
                        $this->ppdLog("updateAllUserBalance DB UPDATE ERROR:" . json_encode($m->getDbError()),3);
                    }
                }
            }//end foreach
        }
    }
    private function updateUserBalance($id, $balance)
    {
        $m=M("user");
        if($m->where("UserId='{$id}'")->find())
        {
            $data['UserBalance']=$balance;
            $data['UBUpdateTime']=date("Y-m-d H:i:s",time());
            $r=$m->where("UserId='{$id}'")->save($data);
            if($r===false)
                $this->ppdLog("UserId:$id,balance:$balance, balance update db  error!",2);
        }else 
            $this->ppdLog("UserId:$id cannot be found, which is wired",3);
    }
    
    private function isUserBalanceJustUpdated($id,$timeout=30)
    {
        $m=M("user");
        if($m->where("UserId='{$id}'")->find())
        {
            if(time()-strtotime($m->UBUpdateTime)>$timeout)
                    return false;
        }else{
            $this->ppdLog("UserBalanceUpdated: UserId:$id cannot be found, which is wired",3);
        }
        return true;
    }
    
    
    private function checkAccesstoken()
    {
        $this->ppdLog("function:" . __FUNCTION__);
        foreach($this->user_balance as $id=>$user)
        {
            if($user->atexpire-time()<3600*4)
            {
                $status = $this->userRefreshToken($id);
                if($status==-1|| $status==-3)
                    $this->disactiveUser($id);
            }
        }
    }
    
    /*检查投标队列，如果TOKEN有效期即将失效（10分钟后），则移出所有队列，直到 TOKEN被更新或者重新扫描才能重新放入等待队列。
     * 并没有检查余额，因为每次投标都会检查余额，只要投标显示余额不足活着投标后余额小于上次投标额，就会被放入等待队列。
    */
    private function checkBidQueue()
    {
        $this->ppdLog("function:" . __FUNCTION__);
        foreach($this->bid_queue as $strategy_id=>$sets)
        {
            foreach($sets as $id=>$setting){
                $user_id=$setting->userid;
                if($this->user_balance[$user_id]->atexpire<time()+600){
                    $this->clearFromQueue($user_id);
                    $this->ppdLog("$user_id TOKEN EXPIRED CLEAR FORM QUEUE" . $this->user_balance[$user_id]->atexpire );
                }else if($this->user_balance[$user_id]->balance < $this->user_balance[$user_id]->minBalance){
                    $this->clearFromQueue($user_id);
                    $this->ppdLog("$user_id min balance reached ! balance:" . $this->user_balance[$user_id]->balance);
                }

            }
        }
        /* 同步时间，防止投标错误*/
        $this->time_diff=$this->getTimeDiff($this->sync_server);
    }
    
    public function disactiveUser($user_id)
    {
        //在数据库中将用户标记为失活，，在用户登录或者复活检查时符合条件可以复活。
        $user=M('User');
        $data['ATExpireDate']=0;
        $data['RTExpireDate']=0;
        $user->where("UserId='{$user_id}'")->save($data);
        if($user===false)
            $this->ppdLog("UserId:$user_id,DISACTIVE ERROR,ERROR INFO:" . $user->getDbError() . "last SQL:" . $user->getLastSql());
        
    }
    /* 检查等待队列，如果TOKEN有效期即将失效（10分钟后），则移出所有队列，直到 TOKEN被更新或者重新扫描才能重新放入等待队列。
     * 如果TOKEN有效并且余额大于100，则移出等待队列，加入投标队列。
    */
    public function dealWithTokenError($error_code,$user_id)
    {
        if($error_code==-400){
            $status=$this->userRefreshToken($user_id);
            if($status==0){
                
            }else if($status==-1 || $status==-3){
                $this->clearFromQueue($user_id);
                //$this->disactiveUser($user_id);
                $this->ppdLog("USER:{$user_id} IS CLEARED FROM QUEUE,STATUS:{status}",1);
            }
        }else{
            $this->ppdLog("IF THIS REPEAT MANY TIMES, WE NEED ADD CODE TO DEAL IT code:$error_code");
        }
        
    }
    public function checkWaitQueue()
    {
        if($this->msectime()-$this->last_time_check_wait_queue<10000)
            return 0;
        $this->last_time_check_wait_queue=$this->msectime();
        foreach($this->wait_queue as $strategy_id=>$sets)
        {
            foreach($sets as $id=>$setting){
                $user_id=$setting->userid;
                if($this->user_balance[$user_id]->atexpire<time()+600){
                    $this->clearFromQueue($user_id);
                    $this->ppdLog("$user_id TOKEN EXPIRED CLEAR FORM QUEUE" . $this->user_balance[$user_id]->atexpire );
                }
                else 
                {
                    $queue_changed=false;
                    unset($this->wait_queue[$strategy_id][$id]);
                    $balance=$this->getBalanceFromDb($user_id);
                    if($balance>=0)
                        $this->user_balance[$user_id]->balance=$balance;
                    if ($this->user_balance[$user_id]->balance > $setting->bidamount*2 && $this->user_balance[$user_id]->Score >  $setting->bidamount*40)
                    {
                        $this->bid_queue[$strategy_id][]=$setting;
                        $this->ppdLog("$user_id MOVE FROM WAIT QUEUE TO BID QUEUE BALANCE:".  $this->user_balance[$user_id]->balance);
                        $queue_changed=true;
                    }else
                        $this->wait_queue[$strategy_id][]=$setting;
                }
                if($this->msectime()-$this->last_time_check_wait_queue>50){
                    return $this->msectime()-$this->last_time_check_wait_queue;
                }
        
            }
        }
        
        return time()-$this->last_time_check_wait_queue;
    }
    private function scanUserAuthOrSettingChange()
    {
        $this->ppdLog("function:" . __FUNCTION__);
        $u=M('User');
        $last_scan=date('Y-m-d H:i:s',$this->last_time_scan_event);
        $this->last_time_scan_event=time();
        //$last_scan=date('Y-m-d H:i:s',0);
        $ids1=$u->where("UpdateTime >'{$last_scan}'")->distinct(true)->field('UserId,AccessToken')->select();
        $s=M('strategy_setting');
        $ids2=$s->where("UpdateTime >'{$last_scan}'")->distinct(true)->field('UserId')->select();
        foreach ($ids1 as $id){
            if(isset($this->user_balance[$id['UserId']]->accesstoken) && $this->user_balance[$id['UserId']]->accesstoken!=$id['AccessToken'])
                $ids[]=$id['UserId'];
        }
        foreach ($ids2 as $id)
            $ids[]=$id['UserId'];
        $ids_unique=array_unique($ids);
        foreach($ids_unique as $id)
            $this->authorizeOrSettingChanged($id);

    }
    
    /*用户更新策略或者认证消息，并将用户策略设置放入等待队列和userbalance
     */
    private function authorizeOrSettingChanged($user_id)
    {
        $this->ppdLog("userid: $user_id:" . __FUNCTION__);

        $user_id = (int)$user_id;
        $user=M("user");
        if($user->where("UserId='{$user_id}'")->find())
        {
                $this->clearFromQueue($user_id);
                $this->user_balance[$user_id]->accesstoken=$user->AccessToken;
                $this->user_balance[$user_id]->atexpire=strtotime($user->ATExpireDate);
                $this->user_balance[$user_id]->balance = $user->UserBalance;
                $user_setting=M('Strategy_setting');
                $data=$user_setting->where("UserId='{$user_id}' AND BidAmount>=50")->field("UserId,StrategyId,BidAmount")->select();
                if($data){
                    foreach ($data as $st){
                        $setting=(object)array();
                        $setting->userid=$st['UserId'];
                        $setting->bidamount=$st['BidAmount'];
                        $this->wait_queue[$st['StrategyId']][]=$setting;
                        unset ($setting);
                    }
                }
        }
        
        
    }
    
    
    
    private function clearFromQueue($user_id)
    {
        $this->ppdLog("function:" . __FUNCTION__);
        foreach($this->bid_queue as $strategyid=>$sets)
        {
            foreach ($sets as $id=>$set)
            {
                if($set->userid==$user_id)
                    unset($this->bid_queue[$strategyid][$id]);
            }
        }
        foreach($this->wait_queue as $strategyid=>$sets)
        {
            foreach ($sets as $id=>$set)
            {
                if($set->userid==$user_id)
                    unset($this->wait_queue[$strategyid][$id]);
            }
        }
        if(isset($this->user_balance[$user_id]))
            unset($this->user_balance[$user_id]);
    }
    
    public function testRandom(){
        $a=$this->msectime();
        $this->scanStrategySetting($this->bidAppIndex);
        $b=$this->msectime();
        print_r($this->bid_queue);
        echo "</br></br>";
        $c=$this->msectime();
        $this->randomBidQueue();
        $d=$this->msectime();
        print_r($this->bid_queue);
        echo "</br>.scantime:".($b-$a)."random:" . ($d-$c) ."</br>";
    }
    
    private function randomBidQueue(){
        foreach($this->bid_queue as $strategy_id=>$sets)
        {
            shuffle($this->bid_queue[$strategy_id]);
        }

    }
    
    private function scanStrategySetting($appindex = 1)
    {

        $this->ppdLog("function:" . __FUNCTION__);
        $this->bid_queue=array();
        $this->wait_queue=array();
        $this->user_balance=array();
        $this->last_time_scan_strategy_setting=time();
        $user=M('Strategy_setting');
        $sql="SELECT ppd_user.UserId,StrategyId,BidAmount,ppd_user.UserBalance,Score,AccessToken, ATExpireDate FROM ppd_strategy_setting RIGHT JOIN ppd_user ON ppd_strategy_setting.userid=ppd_user.userid WHERE BidAmount >=50 ";
        $data=$user->query($sql);
        $mul = M("user_multi_auth");
        $auths = $mul->where("Status = 0 and AppIndex = '{$appindex}'")->select();
        foreach ($data as $st)
        {
            $ATExpireDate = null;
            $AccessToken = null;
            
            foreach ($auths as $auth){
                if($auth['UserId'] == $st['UserId'])
                {
                    $ATExpireDate = $auth['ATExpireDate'];
                    $AccessToken = $auth['AccessToken'];
                }
            }
            
            if( $ATExpireDate && $AccessToken && (strtotime($ATExpireDate)>time()+600))
            {
                $userinfo=new \stdClass();
                $userinfo->accesstoken=$AccessToken;
                $userinfo->atexpire=strtotime($ATExpireDate);
                $userinfo->balance=$st['UserBalance'];
                $userinfo->Score=$st['Score'];
                $userinfo->minRate=0;
                $userinfo->minMonth=0;
                $userinfo->minBalance=0;
                $userinfo->maxMonth = 36;
                $this->user_balance[$st['UserId']]=$userinfo;
        
                if($st['UserBalance']>=$st['BidAmount']&&$st['Score']>$st['BidAmount']*40){
                    $setting=new \stdClass();
                    $setting->userid=$st['UserId'];
                    $setting->bidamount=$st['BidAmount'];
                    $this->bid_queue[$st['StrategyId']][]=$setting;
                    unset ($setting);
                }    
            }

        }
        $ugs=M('user_global_setting');
        $data = $ugs->where(1)->select();
        if ($data === false){
            $this->ppdLog("find user global setting error\n", 3);
        }
        foreach ($data as $us){
            if (isset($this->user_balance[$us['UserId']]))
            {
                $this->user_balance[$us['UserId']]->minRate = $us['MinRate'];
                $this->user_balance[$us['UserId']]->minMonth = $us['MinMonth'];
                $this->user_balance[$us['UserId']]->minBalance = $us['MinBalance'];
                $this->user_balance[$us['UserId']]->maxMonth = $us['MaxMonth'];
            }
        }
        
        $this->randomBidQueue();

//      echo "scanStrategySetting: final</br>";
//      $this->showRunningEnvirenment();
//      echo "</br>";
    }
    
    public function showRunningEnvirenment($is_to_log=false)
    {
        if($is_to_log)
        {
            $this->ppdLog("user_balance:");
            $this->ppdLog(dump($this->user_balance,false));
            $this->ppdLog("bid_queue:");
            $this->ppdLog(dump($this->bid_queue,false));
            $this->ppdLog("wait_queue:");
            $this->ppdLog(dump($this->wait_queue,false));
        }else{
            echo "</br>user_balance:</br>";
            dump($this->user_balance);
            echo "</br>bid_queue:</br>";
            dump($this->bid_queue);
            echo "</br>wait_queue:</br>";
            dump($this->wait_queue);
            echo "</br>end:</br>";
        }
        
    }
    public function testurt($id){
        echo "<meta charset=\"UTF-8\">";
        $status=$this->userRefreshToken($id);
        echo $status;
        
    }
    
    public function MultiUserRefreshToken()
    {
       $mul = M("user_multi_auth");
       $data = $mul->where("Status = 0")->select();
       $app = M("appid");
       $appinfo = $app->where("AppIndex > 1")->select();
       if(!$appinfo){
           $this->ppdLog("No APPINFO FOUND");
           return;
       }
       if($data){
           foreach($data as $auth){
               //如果 刷新令牌已经过期，则置为无效
               $needRefresh = False;
               $uid = $auth['UserId'];
               $appindex = $auth['AppIndex'];
               $appid = null;
               $privatekey = null;
               foreach($appinfo as $ai){
                   if($ai['AppIndex'] == $appindex){
                       $appid  = $ai['AppId'];
                       $privatekey = $ai['PrivateKey'];
                   }
               }
               if($appid == null || $privatekey == null){
                   $this->ppdLog("APPINDEX: $appindex NOT found!!!");
                   continue;
               }
               
               if(strtotime($auth['RTExpireDate'])<(time()+60))
               {
                   $this->ppdLog("USER ID:" . $data["UserId"] . "TOKEN EXPIRED" , 1);
                   $auth['Status'] = -1;
                   $auth['UpdateTime'] = date("Y-m-d H:i:s", time());
                   $status = $mul->where("UserId = '{$uid}' and AppIndex = '{$appindex}'")->save($auth);
                   if($status === false){
                       $this->ppdLog(" UPDATE MULTI AUTH SAVE ERROR");
                   }
                   
               }else if(strtotime($auth['ATExpireDate']) < (time()+3600))
               {
                   echo "user:$uid appindex:$appindex at expired</br>";
                   $needRefresh = True;
               }else{//否则，检查现在TOKEN 是否有效（查询余额）
                   $url = "https://openapi.ppdai.com/balance/balanceService/QueryBalance";
                   $request = '{ }';
                   $r = $this->send($url, $request, $auth['AccessToken'], $appid, $privatekey);
                   if(isset($r['Code']) && $r['Code']=="GTW-BRQ-INVALIDTOKEN")//如果无效，则刷新TOKEN 更新AccessToken ATExpireDate  OpenID
                   {
                       $needRefresh = True;
                       echo "user $uid appindex $appindex token invalid</br>";
                       $this->ppdLog("getBalance ERROR! GTW-BRQ-INVALIDTOKEN </br>\n",2);
                   }else{
                       echo "user $uid appindex $appindex normal</br>";
                   }
               }
               
               
				//如果无效，则刷新TOKEN 更新AccessToken ATExpireDate
				if($needRefresh){
                    echo "uid $uid  appindex: $appindex needRefresh</br>";
				    $r=$this->refreshToken($auth['OpenID'], $auth['RefreshToken'], $appid);
				    if(isset($r['AccessToken'])){
				    	$auth['AccessToken']=$r['AccessToken'];
				    	$auth['ATExpireDate']=date("Y-m-d H:i:s",time()+$r['ExpiresIn']);
				    	$auth['UpdateTime']=date("Y-m-d H:i:s",time());
				    }else if(isset($r['ErrMsg']) && $r['ErrMsg']=="用户与应用关联信息无效"){
                        /*"ErrMsg":"用户与应用关联信息无效"*/
				        $auth['UpdateTime'] = date("Y-m-d H:i:s", time());
				        $auth['Status'] = -1;
				    }else{
				        $this->ppdLog("uid $uid appindex $appindex UPDATE MULTI AUTH REFRESH UNKNOWN ERROR" . json_encode($r));
				    }
				    $status = $mul->where("UserId = '{$uid}' and AppIndex = '{$appindex}'")->save($auth);
				    if($status === false){
				    	$this->ppdLog("uid $uid appindex $appindex UPDATE MULTI AUTH SAVE ERROR");
				    }
				}
           }
       }else{
           if($data === false)
               $this->ppdLog("MultiUserRefreshToken DB ERROR");
           else 
               $this->ppdLog("MultiUserRefreshToken No Record found");
       }
    }
    
    private function  userRefreshToken($user_id)
    {
        $this->ppdLog("user_id:$user_id:" . __FUNCTION__);
        $user_id=(int)$user_id;
        $user=M('user');
        $data=$user->where("UserId='{$user_id}'")->find();
        if($data)
        {
            if(strtotime($data['RTExpireDate'])<(time()+60)){
                return -1;
                $this->ppdLog("USER ID:" . $user_id . "TOKEN EXPIRED" , 1);
            }
            $r=$this->refreshToken($data['OpenID'], $data['RefreshToken']);
            if(isset($r['AccessToken'])){
                $data['AccessToken']=$r['AccessToken'];
                $data['ATExpireDate']=date("Y-m-d H:i:s",time()+$r['ExpiresIn']);
                $data['UpdateTime']=date("Y-m-d H:i:s",time());
                if(isset($this->user_balance[$user_id])){
                    $this->user_balance[$user_id]->accesstoken=$data['AccessToken'];
                    $this->user_balance[$user_id]->atexpire=time()+$r['ExpiresIn'];
                }
                $status=$user->where("UserId='{$user_id}'")->save($data);
                if(!$status)
                {
                    $this->ppdLog("USER ID:" . $user_id . "DB UPDATE TOKEN ERROR!" , 2);
                    return -2;
                }else{
                    return 0;
                }
            }else{
                if(isset($r['ErrMsg']))
                    $this->ppdLog("USER ID:" . $user_id . "TOKEN REFRESH FAILED! MSG:" . $r['ErrMsg'] , 2);
                else 
                    $this->ppdLog("USER ID:" . $user_id . "TOKEN REFRESH FAILED! UNKNOWN REASON" , 2);
                return -3;
            }
        }else{
            return -4;
            $this->ppdLog("CAN'T FIND USER: $user_id ", 3);
        }
    }
    
    public function testNewGetLoanList(){
        $this->time_diff=$this->getTimeDiff($this->sync_server);
        $this->last_time_get_loan_list=$this->mSyncTime($this->time_diff)-100000;
        for($i=0;$i<20;$i++){
            $data=$this->getLoanList();
            print_r($data);
            echo "</br></br>";
            usleep(100000);
        }
    }

    //2015-11-11 12:00:00.000
    private function getLoanList(){
        //获取所有借款列表，API 每页200条，获取到 每页不是200条表明是最后一页，将所有列表合成为一个，返回列表，如果失败，返回空表。
        /*新版投标列表接口（默认每页200条）*/
        $page_index = 1;
        $loan_list_array=array();
        $last_time=$this->last_time_get_loan_list-10000;
        $this->last_time_get_loan_list=$this->mSyncTime($this->time_diff);
        //$atime=$this->msectime();
        
        do {
            $list=array();
            $url = "https://openapi.ppdai.com/listing/openapiNoAuth/loanList";
            $request = '{
            "PageIndex": ' . $page_index . ',
            "StartDateTime": "'. date("Y-m-d H:i:s",$last_time/1000)  . '.' . $last_time%1000 . '"
            }';
            $result = $this->multiAccountSend($url, $request);
            if((isset( $result['Result'])&& $result['Result']==1))
            {
                $list= $result['LoanInfos'];
            }else {
                $this->last_time_get_loan_list=$last_time+1;//失败请求时间区间复盘
                if(json_encode($result)==false||json_encode($result)==null)
                    $this->ppdLog("getLoanList result ERR:" . $result,2);
                else
                    $this->ppdLog("getLoanList json result ERR:" . json_encode($result),2);
                    
            }
            $loan_list_array=array_merge($loan_list_array,$list);
            $page_index = $page_index+1;
        }while (count($list)>=200);
        //$btime=$this->msectime();
        //$this->ppdLog("getLoanList TimeSpan" . ($btime-$atime) . "count:" . count($loan_list_array) );
        return  $loan_list_array;
    }

    public function dumpLoanInfo($id, $id2 = 0, $id3 = 0){
        $ids[]=$id;
        if($id2 >0){
            $ids[]=$id2;
        }
        if($id3 >0){
            $ids[]=$id3;
        }
        dump($this->getLoanDetail($ids));
        $redis = Pedis::getBidRedis(1);
        $redis->publish("channelDetailInfoTest", json_encode($this->getLoanDetail($ids)));

    }
    //返回load_detail_list
    /*新版散标详情批量接口（请求列表不大于10）*/
    private function getLoanDetail($loan_id_list){
        $url = "https://openapi.ppdai.com/listing/openapiNoAuth/batchListingInfo";
        $request= '{ "ListingIds": [';
        foreach ($loan_id_list as $id){
            $request=$request . $id . ',';
        }
        $request = rtrim($request, ",");
        $request= $request  . '] }';
        $result = $this->send($url, $request);
        if(isset($result['Result']) && $result['Result']==1 )
        {
            return $result['LoanInfos'];
        }else{
            if(json_encode($result))
                $this->ppdLog("getLoanDetail ERR:" . json_encode($result),2);
            else
                $this->ppdLog("getLoanDetail ERR:" . json_encode($result),2);
            return array();
        }
    }
    
    private function matchBasicStrategy($loan_basic_info){
        //根据借款列表的基本信息过滤标的
        //合适返回标的LIST ID， 否则返回 0
        if(isset($loan_basic_info['ListingId'])){
            $rate_filter=$loan_basic_info['Rate']<= 15 || $loan_basic_info['Rate']>36;
            $month_filter=$loan_basic_info['Months']>36;
            $pay_way_filter=$loan_basic_info['PayWay']==1;
            if(!($rate_filter||$month_filter||$pay_way_filter))
                return $loan_basic_info['ListingId'];
        }
        return 0;
    
    }
    
    private function isGraduateFrom985($school)
    {
        $school_name_985=array('清华大学','厦门大学','南京大学','天津大学','浙江大学','西安交通大学','东南大学','上海交通大学','山东大学','中国人民大学','吉林大学','电子科技大学','四川大学','华南理工大学','兰州大学','西北工业大学','同济大学','北京大学','中国科学技术大学','复旦大学','哈尔滨工业大学','南开大学','华中科技大学','武汉大学','中国海洋大学','湖南大学','北京理工大学','重庆大学','大连理工大学','中山大学','北京航空航天大学','东北大学','北京师范大学','中南大学','中国农业大学','西北农林科技大学','中央民族大学','国防科技大学','华东师范大学');
        return (in_array($school, $school_name_985,true));
        
    }
    
    private function isGraduateFrom211($school)
    {
        $school_name_211=array('北京工业大学','清华大学','北京大学','中国人民大学','南京理工大学','西安交通大学','北京化工大学','北京理工大学','北京航空航天大学','上海外国语大学','南京农业大学',
            '西安电子科技大学','中国传媒大学','北京邮电大学','对外经济贸易大学','天津医科大学 ','浙江大学','海南大学','中央财经大学','中央民族大学','中国矿业大学(北京)',
            '辽宁大学','安徽大学','宁夏大学','中央音乐学院','中国政法大学','中国石油大学(北京)','延边大学','厦门大学','青海大学','北京交通大学','北京体育大学','北京外国语大学',
            '哈尔滨工业大学','南昌大学','西藏大学','中国农业大学','北京科技大学','北京林业大学','苏州大学','山东大学','陕西师范大学','北京师范大学','北京中医药大学',
            '华北电力大学(北京)','中国科学技术大学','郑州大学','长安大学','上海大学','中国地质大学(北京)','华东师范大学','中国石油大学(华东)','武汉大学','南京大学',
            '东华大学','复旦大学','华东理工大学','中国地质大学(武汉)','华中农业大学','中国药科大学','河北工业大学','同济大学','上海交通大学','湖南师范大学','湖南大学',
            '福州大学','大连海事大学','上海财经大学','天津大学','华南理工大学','中山大学',' 石河子大学','哈尔滨工程大学','南开大学','西南大学','电子科技大学','第二军医大学',
            '中南大学','河海大学','重庆大学','东北大学','西北工业大学','广西大学','暨南大学','南京师范大学','华北电力大学(保定)','东北师范大学','国防科学技术大学','四川大学',
            '兰州大学','江南大学','太原理工大学','东北林业大学','中国海洋大学','四川农业大学','新疆大学','华中师范大学','内蒙古大学','东南大学','西南交通大学','云南大学','武汉理工大学',
            '大连理工大学','中国矿业大学(徐州)','华中科技大学','贵州大学','华南师范大学','吉林大学','南京航空航天大学','中南财经政法大学','西北大学','西南财经大学','东北农业大学',
            '合肥工业大学','第四军医大学','西北农林科大');
        return (in_array($school, $school_name_211,true));
    
    }
    
    // strategy 1-6 are safe strategy. only basic info can decide.
    public function matchStrategy1($loan_detail_info){
        //匹配返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['Rate']>=11 && $loan_detail_info['Rate']<12 && $loan_detail_info['CreditCode']=='AA' && $loan_detail_info['Months']<=1){
                $id=$loan_detail_info['ListingId'];
            }
        }
        return $id;
    }
    
    public function matchStrategy2($loan_detail_info){
        //匹配返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['Rate']>=11 && $loan_detail_info['Rate']<12 && $loan_detail_info['CreditCode']=='AA' && $loan_detail_info['Months']>1 && $loan_detail_info['Months']<=3){
                $id=$loan_detail_info['ListingId'];
            }
        }
        return $id;
    }
    
    public function matchStrategy3($loan_detail_info){
        //匹配返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['Rate']>=12 && $loan_detail_info['Rate']<12.5 && $loan_detail_info['CreditCode']=='AA' && $loan_detail_info['Months']<12){
                $id=$loan_detail_info['ListingId'];
            }
        }
        return $id;
    }
    public function matchStrategy4($loan_detail_info){
        //匹配返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['Rate']>=12.5 && $loan_detail_info['Rate']<13 && $loan_detail_info['CreditCode']=='AA' && $loan_detail_info['Months']<=18){
                $id=$loan_detail_info['ListingId'];
            }
        }
        return $id;
    }
    private function matchStrategy5($loan_detail_info){
        //匹配返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['CreditCode']=='AA' && $loan_detail_info['Rate']>=13 && $loan_detail_info['Rate']<15  && $loan_detail_info['Months']<=24){
                $id=$loan_detail_info['ListingId'];
            }
        }
        return $id;
    }
    
    private function matchStrategy6($loan_detail_info){
        //匹配返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['CreditCode']=='AA' && $loan_detail_info['Rate']>=15 && $loan_detail_info['Months']<=36){
                $id=$loan_detail_info['ListingId'];
            }
        }
        return $id;
    }
    private function matchStrategy7($loan_detail_info){
        //匹配返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['CreditCode']=='AA' && $loan_detail_info['Rate']==14 && $loan_detail_info['Months']<=36){
                $id=$loan_detail_info['ListingId'];
            }
        }
        return $id;
    }
    private function matchStrategy1000($loan_detail_info){
        //匹配返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['CreditCode']=='AA' && $loan_detail_info['Rate']==9){
                $id=$loan_detail_info['ListingId'];
            }
        }
        return $id;
    }
    private function matchStrategy1001($loan_detail_info){
        //匹配返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['CreditCode']=='AA' && $loan_detail_info['Rate']==9.5){
                $id=$loan_detail_info['ListingId'];
            }
        }
        return $id;
    }
    private function matchStrategy1002($loan_detail_info){
        //匹配返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['CreditCode']=='AA' && $loan_detail_info['Rate']==10){
                $id=$loan_detail_info['ListingId'];
            }
        }
        return $id;
    }
    private function matchStrategy1004($loan_detail_info){
        //匹配返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['CreditCode']=='AA' && $loan_detail_info['Rate']==11){
                $id=$loan_detail_info['ListingId'];
            }
        }
        return $id;
    }
    private function matchStrategy1006($loan_detail_info){
        //匹配返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['CreditCode']=='AA' && $loan_detail_info['Rate']==12){
                $id=$loan_detail_info['ListingId'];
            }
        }
        return $id;
    }
    private function matchStrategy1007($loan_detail_info){
        //匹配返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['CreditCode']=='AA' && $loan_detail_info['Rate']==12.5){
                $id=$loan_detail_info['ListingId'];
            }
        }
        return $id;
    }
    private function matchStrategy1008($loan_detail_info){
        //匹配返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['CreditCode']=='AA' && $loan_detail_info['Rate']==13){
                $id=$loan_detail_info['ListingId'];
            }
        }
        return $id;
    }
    private function matchStrategy1010($loan_detail_info){
        //匹配返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['CreditCode']=='AA' && $loan_detail_info['Rate']==14){
                $id=$loan_detail_info['ListingId'];
            }
        }
        return $id;
    }

    //  "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1 AND  SCHOOL_985 " 
    // total:" 823 delay_ratio: 0.243013 expect_rate: 20.4144 HDRD: 0.635663
    private function matchStrategy10($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['CurrentRate']==20 && $loan_detail_info['Months']==6){
                $creditvalid=$loan_detail_info['CreditValidate']==1;
                $graduate985=$this->isGraduateFrom985($loan_detail_info['GraduateSchool']);
                if($creditvalid && $graduate985 && $loan_detail_info['StudyStyle']!='网络教育')
                    $id=$loan_detail_info['ListingId'];
            }
    
        }
        return $id;
    }

    //ruler: "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1 AND `StudyStyle` is not null AND `Age` BETWEEN 18 and 28 AND `OverdueLessCount`/(`NormalCount`+1)<0.03"
    //ok: 3814 delay:17 delay_ratio: 0.55 expect_rate: 19.12
    private function matchStrategy11($loan_detail_info){
        //根据借款详情匹配标的 匹配公式：20% 6个月  有学历   征信认证且视频认证 年龄 18-28岁。
        //预期逾期率0.82  预期年化收益率 17.4%
        //合适返回标的LIST ID， 否则返回 0
        //add SuccessCount>1 2017/7/21
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['CurrentRate']==20 && $loan_detail_info['Months']==6 && $loan_detail_info['CreditValidate']==1){
                if($loan_detail_info['SuccessCount']>1 && $loan_detail_info['StudyStyle']!=null  && $loan_detail_info['Age']>=18  && $loan_detail_info['Age']<=28 &&  $loan_detail_info['OverdueLessCount']/($loan_detail_info['NormalCount']+0.01)<0.03 )
                    $id=$loan_detail_info['ListingId'];
            }
    
        }
        return $id;
    }
    
    //ruler: "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1 AND `EducationDegree`='本科' AND `StudyStyle` IN ('普通','成人','研究生') AND `Age`>32 AND `OverdueLessCount`/(`NormalCount`+1)<0.03" 
    //3831 : ok: 3814 delay:17 delay_ratio: 0.7 expect_rate: 18.49
    private function matchStrategy12($loan_detail_info){
        //根据借款详情匹配标的 匹配公式：20% 6个月  本科学历   学习形式：常规，年龄大于32.
        //预期逾期率0.42  预期年化收益率 19.33%
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId'])){
            if($loan_detail_info['CurrentRate']==20 && $loan_detail_info['Months']==6 && $loan_detail_info['CreditValidate']==1){
                if($loan_detail_info['EducationDegree']=='本科' && $loan_detail_info['Age']>32  &&   $loan_detail_info['OverdueLessCount']/($loan_detail_info['NormalCount']+0.01)<0.03){
                    $style=$loan_detail_info['StudyStyle'];
                    if($style=='普通' || $style=='成人')
                        $id=$loan_detail_info['ListingId'];
                }
            }
    
        }
        return $id;
    }
    
    

    //ruler: "`CurrentRate`=18 AND `Months`= 6 AND `CreditValidate`= 1 AND `EducationDegree`='本科' AND `Age`>=18 AND `Age`<=32 AND `CreditCode` ='B'  AND `OverdueLessCount`/(`NormalCount`+1)<0.03 "
    //1000 : ok: 998delay: 2 delay_ratio: 0.2 expect_rate: 18.33
    private function matchStrategy13($loan_detail_info){
        //根据借款详情匹配标的 匹配公式：18% 6个月  本科学历    征信认证， 魔镜 B,C D 常规，年龄18-32.
        //预期逾期率0.53  预期年化收益率 16%
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==18 && $loan_detail_info['Months']==6 && $loan_detail_info['CreditValidate']==1)
            {
                if($loan_detail_info['EducationDegree']=='本科' && $loan_detail_info['Age']>=18 && $loan_detail_info['Age']<=32 && $loan_detail_info['CreditCode']=='B' &&   $loan_detail_info['OverdueLessCount']/($loan_detail_info['NormalCount']+0.01)<0.03 )
                {
                        $id=$loan_detail_info['ListingId'];
                }
                
            }
    
        }
        return $id;
    }
    
    //ruler: " `Months`= 6 AND `CreditValidate`= 1 AND `EducateValidate`= 1 AND `OverdueLessCount`/(`NormalCount`+1)<0.03 "
    //1740: ok: 1730 delay: 10 delay_ratio: 0.6 expect_rate: 17.79
    private function matchStrategy14($loan_detail_info){
        //18-20混合标
        //预期年化收益率 18.9%
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==20 && $loan_detail_info['Months']==6 && $loan_detail_info['CreditValidate']==1 && $loan_detail_info['OverdueLessCount']/($loan_detail_info['NormalCount']+0.01)<0.03)
            {
                if($loan_detail_info['EducateValidate']==1)
                {
                    $id=$loan_detail_info['ListingId'];
                }
    
            }
    
        }
        return $id;
    }
    
//END total:" 636 delay_ratio: 0.157233 expect_rate: 18.5145 HDRD: 0.606723
//ruler:" "`CurrentRate`=18 AND `Months`= 6 AND `CreditValidate`= 1 AND `Gender`= 2 AND `CreditCode` IN ('B','C') AND   `EducationDegree`=BK AND " 
    private function matchStrategy15($loan_detail_info){
        //根据借款详情匹配标的 匹配公式：20% 6个月  户籍认证    征信认证 视频认证 魔镜 BC.
        //预期逾期率1.14 预期年化收益率 15.78%
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==18 && $loan_detail_info['Months']==6 && $loan_detail_info['CreditValidate']==1)
            {
                if($loan_detail_info['Gender']==2 && $loan_detail_info['EducationDegree']=='本科')
                { 
                    if($loan_detail_info['CreditCode']=='B' || $loan_detail_info['CreditCode']=='C')
                        $id=$loan_detail_info['ListingId'];
                }
    
            }
    
        }
        return $id;
    }

//--MID total:" 1474 delay_ratio: 0.54  expect_rate: 19.16 HDRD: 0.73951
//ruler:" "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1  AND `Age`>=24 AND `Age`<=28 AND  `EducationDegree`=BK AND `OverdueLessCount`/(`NormalCount`+1)<0.03 AND 

    private function matchStrategy16($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==20  && $loan_detail_info['Months']==6 && $loan_detail_info['CreditValidate']==1)
            {
                if($loan_detail_info['EducationDegree']=='本科' && $loan_detail_info['Age']>=24 && $loan_detail_info['Age']<=28 && $loan_detail_info['OverdueLessCount']/($loan_detail_info['NormalCount']+0.01)<0.03 )
                {
                    $id=$loan_detail_info['ListingId'];
                }
    
            }
    
        }
        return $id;
    }
    //`CurrentRate`=18 AND `EducationDegree` ='硕士研究生' AND `Months`= 6 AND `CreditCode` IN ('B','C','D') AND `Age`>=18 AND `Age`<=35 AND
    // 333 : ok: 333 delay: 0 delay_ratio: 0 expect_rate: 19.16 
    private function matchStrategy17($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==18  && $loan_detail_info['Months']==6 && $loan_detail_info['EducationDegree']=='硕士研究生' && $loan_detail_info['Age']>=18 && $loan_detail_info['Age']<=35)
            {
                if($loan_detail_info['CreditCode']=='B' || $loan_detail_info['CreditCode']=='C' ||$loan_detail_info['CreditCode']=='D')
                {
                    $id=$loan_detail_info['ListingId'];
                }
    
            }
    
        }
        return $id;
    }
    private function matchStrategy18($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['SuccessCount']>10 && $loan_detail_info['Months']==6 && $loan_detail_info['CurrentRate']>18 && $loan_detail_info['OwingAmount']+$loan_detail_info['Amount']<$loan_detail_info['HighestDebt']*0.5 &&  $loan_detail_info['OverdueLessCount']/($loan_detail_info['NormalCount']+0.01)<0.03 )
            {
                    $id=$loan_detail_info['ListingId'];
            }
    
        }
        return $id;
    }
    //END total:" 768 delay_ratio: 0.520833 expect_rate: 17.0145 HDRD: 0.716254
//ruler:" "`CurrentRate`=18 AND `Months`= 6 AND `CreditValidate`= 1 AND `EducateValidate`= 1 AND " 

    private function matchStrategy19($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==18  && $loan_detail_info['Months']==6 && $loan_detail_info['CreditValidate']==1 && $loan_detail_info['EducateValidate']==1 )
            {

                    $id=$loan_detail_info['ListingId'];

            }
    
        }
        return $id;
    }
    
    //END total:" 927 delay_ratio: 0.647249 expect_rate: 18.718 HDRD: 0.73359
    //ruler:" "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1  AND `Age`>=24 AND `Age`<=37 AND `Gender`= 2 AND  `EducationDegree`=BK AND "
    private function matchStrategy20($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==20  && $loan_detail_info['Months']==6 && $loan_detail_info['CreditValidate']==1 )
            {
                if($loan_detail_info['Gender']==2 && $loan_detail_info['EducationDegree']=='本科' && $loan_detail_info['Age']>=24 && $loan_detail_info['Age']<=37)
                    $id=$loan_detail_info['ListingId'];
    
            }
    
        }
        return $id;
    }
    
    
    //END total:" 456 delay_ratio: 0.438596 expect_rate: 19.5903 HDRD: 0.716466
    //ruler:" "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1 AND `CertificateValidate`= 1 AND `EducateValidate`= 1 AND "
    private function matchStrategy21($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==20  && $loan_detail_info['Months']==6 && $loan_detail_info['CreditValidate']==1 && $loan_detail_info['EducateValidate']==1 )
            {
                if($loan_detail_info['CertificateValidate']==1 )
                    $id=$loan_detail_info['ListingId'];
    
            }
    
        }
        return $id;
    }
    //MID total:" 2742 delay_ratio: 0.911743 expect_rate: 17.6225 HDRD: 0.734497
    //ruler:" "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1 AND `Age`>=24 AND `Age`<=37 AND `StudyStyle` IN (PCY) AND `Gender`= 2 AND "
    private function matchStrategy22($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==20  && $loan_detail_info['Months']==6 && $loan_detail_info['CreditValidate']==1  )
            {
                if($loan_detail_info['Gender']==2 && $loan_detail_info['Age']>=24 && $loan_detail_info['Age']<=37 ){
                    if($loan_detail_info['StudyStyle']=='普通 '|| $loan_detail_info['StudyStyle']=='成人'||$loan_detail_info['StudyStyle']=='研究生')
                        $id=$loan_detail_info['ListingId'];
                }
    
            }
    
        }
        return $id;
    }
//  ----strategy23 MID total-update:"  4000 delay_ratio: 0.6 expect_rate: 18.9 HDRD: 0.735701
//  --ruler:" "`CurrentRate`=20 AND `Months`= 6 AND `CreditValidate`= 1 AND `CertificateValidate`= 1 AND  `EducationDegree` is not null AND `Age`>=24 AND `Age`<=28 AND `OverdueLessCount`/(`NormalCount`+1)<0.03 AND "
//  --
    private function matchStrategy23($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==20  && $loan_detail_info['Months']==6 && $loan_detail_info['CreditValidate']==1  )
            {
                if($loan_detail_info['CertificateValidate']==1 && $loan_detail_info['EducationDegree']!=null && $loan_detail_info['Age']>=24 && $loan_detail_info['Age']<=28 ){
                    if($loan_detail_info['OverdueLessCount']/($loan_detail_info['NormalCount']+0.01)<0.03)
                        $id=$loan_detail_info['ListingId'];
                }
    
            }
    
        }
        return $id;
    }
    
//  ---strategy24 MID total:" 1688 delay_ratio: 0.414692 expect_rate: 17.4502 HDRD: 0.658732
//  --ruler:      ruler:" "`CurrentRate`=18 AND `Months`= 6 AND `CreditValidate`= 1 AND `CertificateValidate`= 1 AND `Gender`= 2 AND `CreditCode` IN ('B','C') AND  "
    private function matchStrategy24($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==18  && $loan_detail_info['Months']==6 && $loan_detail_info['CreditValidate']==1  )
            {
                if($loan_detail_info['Gender']==2 && $loan_detail_info['CertificateValidate']==1 ){
                    if($loan_detail_info['CreditCode']=='B'|| $loan_detail_info['CreditCode']=='C')
                        $id=$loan_detail_info['ListingId'];
                }
    
            }
    
        }
        return $id;
    }

//  "    低息非首借0逾期MID total:" 1152 delay_ratio: 0.173611 expect_rate: 16.4945 HDRD: 0.587807
//  "    ruler:" " `CurrentRate`= 16 AND  `Months`= 6 AND `CertificateValidate`= 1 AND  (`OverdueLessCount`/(`OverdueLessCount`+`NormalCount`+0.01)) = 0  AND  `NormalCount` > 0 AND "
    private function matchStrategy25($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==15  && $loan_detail_info['Months']==6 && $loan_detail_info['CertificateValidate']==1  )
            {
                if($loan_detail_info['OverdueLessCount']==0 && $loan_detail_info['NormalCount']>0 ){
                        $id=$loan_detail_info['ListingId'];
                }
    
            }
    
        }
        return $id;
    }
    
//  低息学历0逾期"    END total:" 539 delay_ratio: 0 expect_rate: 17.2271 HDRD: 0
//  "    ruler:" " `CurrentRate`= 16 AND  `Months`= 6 AND `CertificateValidate`= 1 AND  (`OverdueLessCount`/(`OverdueLessCount`+`NormalCount`+0.01)) = 0  AND  `OwingPrincipal`/`TotalPrincipal` BETWEEN 0.2 AND 0.6
    
//  AND "
    
    

    private function matchStrategy26($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==15  && $loan_detail_info['Months']==6 && $loan_detail_info['CertificateValidate']==1 &&$loan_detail_info['OverdueLessCount']==0  )
            {
                $owing=$loan_detail_info['OwingPrincipal']/$loan_detail_info['TotalPrincipal'];
                if($owing>0.2&& $owing<0.6){
                    $id=$loan_detail_info['ListingId'];
                }
    
            }
    
        }
        return $id;
    }
    
// 漫界无逾"        END total:" 749 delay_ratio: 0.267023 expect_rate: 18.408 HDRD: 0.648837
// "        ruler:" " `CurrentRate`= 18 AND  `Months`= 6 AND `CertificateValidate`= 1 AND `Age`>=24 AND `Age`<=29 AND  `OwingPrincipal`/`TotalPrincipal` BETWEEN 0.2 AND 0.6 AND  ((`NormalCount` + 

// `OverdueLessCount`)/`SuccessCount`) BETWEEN 3 AND 5.99 AND  (`OverdueLessCount`/(`OverdueLessCount`+`NormalCount`+0.01)) = 0  AND " 
    private function matchStrategy27($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==18  && $loan_detail_info['Months']==6 && $loan_detail_info['CertificateValidate']==1 &&$loan_detail_info['OverdueLessCount']==0  )
            {
                if($loan_detail_info['Age']>24&&$loan_detail_info['Age']<29){
                    $owing=$loan_detail_info['OwingPrincipal']/$loan_detail_info['TotalPrincipal'];
                    $rps=($loan_detail_info['NormalCount']+$loan_detail_info['OverdueLessCount'])/$loan_detail_info['SuccessCount'];
                    if($owing>=0.2 && $owing<=0.6 && $rps>=3 && $rps<6){
                        $id=$loan_detail_info['ListingId'];
                    }
                }
    
            }
    
        }
        return $id;
    }
    
// 飞首无逾 MID total:" 1016 delay_ratio: 0.590551 expect_rate: 17.0294 HDRD: 0.719889
//  "        ruler:" " `CurrentRate`= 18 AND  `Months`= 6 AND `CertificateValidate`= 1 AND `Age`>=24 AND `Age`<=29 AND  `OwingPrincipal`/`TotalPrincipal` BETWEEN 0.2 AND 0.6 AND  `NormalCount` > 0 AND
    
//  (`OverdueLessCount`/(`OverdueLessCount`+`NormalCount`+0.01)) = 0  AND "
    private function matchStrategy28($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==18  && $loan_detail_info['Months']==6 && $loan_detail_info['CertificateValidate']==1 &&$loan_detail_info['OverdueLessCount']==0  )
            {
                if($loan_detail_info['Age']>24&&$loan_detail_info['Age']<29&&$loan_detail_info['NormalCount']>0){
                    $owing=$loan_detail_info['OwingPrincipal']/$loan_detail_info['TotalPrincipal'];
                    if($owing>=0.2 && $owing<=0.6 ){
                        $id=$loan_detail_info['ListingId'];
                    }
                }
    
            }
    
        }
        return $id;
    }

//  "  漫界飞首      END total:" 859 delay_ratio: 0.698487 expect_rate: 18.8733 HDRD: 0.744874
//  "        ruler:" " `CurrentRate`= 20 AND  `Months`= 6 AND `CertificateValidate`= 1 AND `CreditCode` IN ('B','C','D') AND   `NormalCount` > 0 AND  ((`NormalCount` + `OverdueLessCount`)/`SuccessCount`) BETWEEN
    
//  6 AND 11.99 AND  `OwingPrincipal`/`TotalPrincipal` BETWEEN 0.2 AND 0.6 AND "
    private function matchStrategy29($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==20  && $loan_detail_info['Months']==6 && $loan_detail_info['CertificateValidate']==1 &&$loan_detail_info['NormalCount']>0  )
            {
                if($loan_detail_info['CreditCode']=='B' || $loan_detail_info['CreditCode']=='C'||$loan_detail_info['CreditCode']=='D'){
                    $owing=$loan_detail_info['OwingPrincipal']/$loan_detail_info['TotalPrincipal'];
                    $rps=($loan_detail_info['NormalCount']+$loan_detail_info['OverdueLessCount'])/$loan_detail_info['SuccessCount'];
                    if($owing>=0.2 && $owing<=0.6 && $rps>=6 && $rps<12){
                        $id=$loan_detail_info['ListingId'];
                    }
                }
    
            }
    
        }
        return $id;
    }
    private function matchStrategy30($loan_detail_info){
     //根据借款详情匹配标的 匹配公式：18% 6个月  本科学历    征信认证， 魔镜 B,C D 常规，年龄18-32.
     //预期逾期率0.53  预期年化收益率 16%
     //合适返回标的LIST ID， 否则返回 0
     $id =0;
     if(isset($loan_detail_info['ListingId']))
     {
            if($loan_detail_info['CurrentRate']==22  && $loan_detail_info['CreditValidate']==1 && $loan_detail_info['NormalCount'] >0)
            {
                if($loan_detail_info['EducationDegree']=='本科' && $loan_detail_info['Age']>=18 && $loan_detail_info['Age']<=32 && $loan_detail_info['CreditCode']=='B' &&   $loan_detail_info['OverdueLessCount']/$loan_detail_info['NormalCount']<0.03 )
                {
                        $id=$loan_detail_info['ListingId'];
                }

            }

        }
        return $id;
     }

     private function matchStrategy31($loan_detail_info){
        //根据借款详情匹配标的 匹配公式：20% 6个月  户籍认证    征信认证 视频认证 魔镜 BC.
        //预期逾期率1.14 预期年化收益率 15.78%
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==22  && $loan_detail_info['CreditValidate']==1)
            {
                if($loan_detail_info['Gender']==2 && $loan_detail_info['EducationDegree']=='本科')
                {
                    if($loan_detail_info['CreditCode']=='B' )
                        $id=$loan_detail_info['ListingId'];
                }

            }

        }
        return $id;
    }

    private function matchStrategy32($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['CurrentRate']==22  && $loan_detail_info['CreditValidate']==1  )
            {
                if($loan_detail_info['Gender']==2 && $loan_detail_info['CertificateValidate']==1 ){
                    if($loan_detail_info['CreditCode']=='B'|| $loan_detail_info['CreditCode']=='C')
                        $id=$loan_detail_info['ListingId'];
                }

            }

        }
        return $id;
    }
    
    private function matchStrategy33($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        //策略 大额慢借
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['Amount'] > 16000 && $loan_detail_info['PhoneValidate']==1  && $loan_detail_info['SuccessCount']>0)
            {
                $br=$loan_detail_info['NormalCount']/($loan_detail_info['SuccessCount']+0.001);
                $dr = $loan_detail_info['OverdueLessCount']/($loan_detail_info['NormalCount']+0.001);
                if($loan_detail_info['OverdueMoreCount'] == 0 && $loan_detail_info['Age']>=23 && $br >=6 && $dr < 0.2){
                        $id=$loan_detail_info['ListingId'];
                }
            }
        }
        return $id;
    }
    
    private function matchStrategy34($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        //策略 大额有学
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['Amount'] > 15000 && $loan_detail_info['PhoneValidate']==1  && $loan_detail_info['EducationDegree']!=null)
            {
                $br=$loan_detail_info['NormalCount']/($loan_detail_info['SuccessCount']+0.001);
                $dr = $loan_detail_info['OverdueLessCount']/($loan_detail_info['NormalCount']+0.001);
                if($loan_detail_info['OverdueMoreCount'] == 0 && $loan_detail_info['Age']>=23 && $br >=5 && $dr < 0.2){
                    $id=$loan_detail_info['ListingId'];
                }
            }
        }
        return $id;
    }
    
    private function matchStrategy35($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        //策略 低债有学
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['PhoneValidate']==1  && $loan_detail_info['OverdueMoreCount'] == 0 && $loan_detail_info['Age']>=23)
            {
                if($loan_detail_info['EducationDegree']!=null){
                    $br=$loan_detail_info['NormalCount']/($loan_detail_info['SuccessCount']+0.001);
                    $dr = $loan_detail_info['OverdueLessCount']/($loan_detail_info['NormalCount']+0.001);
                    $bor = $loan_detail_info['OwingAmount']/($loan_detail_info['HighestDebt']+0.001);
                    $chr = $loan_detail_info['Amount']/($loan_detail_info['HighestPrincipal']+0.001);
                    if($br >=6 && $dr < 0.2 && $bor <0.5 && $chr <=2){
                        $id=$loan_detail_info['ListingId'];
                    }
                }
            }
        }
        return $id;
    }
    
    private function matchStrategy36($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        //策略 低债三无
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['PhoneValidate']==1  && $loan_detail_info['OverdueMoreCount'] == 0 && $loan_detail_info['Age']>=23)
            {
                if($loan_detail_info['EducationDegree']==null){
                    $br=$loan_detail_info['NormalCount']/($loan_detail_info['SuccessCount']+0.001);
                    $dr = $loan_detail_info['OverdueLessCount']/($loan_detail_info['NormalCount']+0.001);
                    $bor = $loan_detail_info['OwingAmount']/($loan_detail_info['HighestDebt']+0.001);
                    $chr = $loan_detail_info['Amount']/($loan_detail_info['HighestPrincipal']+0.001);
                    if($br >=6 && $dr < 0.2 && $bor <0.5 && $chr <=2){
                        $id=$loan_detail_info['ListingId'];
                    }
                }
            }
        }
        return $id;
    }
    
    private function matchStrategy37($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        //策略 低债征信
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['PhoneValidate']==1  && $loan_detail_info['OverdueMoreCount'] == 0 && $loan_detail_info['Age']>=23)
            {
                if($loan_detail_info['CreditValidate']==1){
                    $br=$loan_detail_info['NormalCount']/($loan_detail_info['SuccessCount']+0.001);
                    $dr = $loan_detail_info['OverdueLessCount']/($loan_detail_info['NormalCount']+0.001);
                    $bor = $loan_detail_info['OwingAmount']/($loan_detail_info['HighestDebt']+0.001);
                    $chr = $loan_detail_info['Amount']/($loan_detail_info['HighestPrincipal']+0.001);
                    if($br >=6 && $dr < 0.2 && $bor <0.5 && $chr <=2){
                        $id=$loan_detail_info['ListingId'];
                    }
                }
            }
        }
        return $id;
    }
    
    private function matchStrategy38($loan_detail_info){
        //合适返回标的LIST ID， 否则返回 0
        //策略 低债高息 22及 以上。
        $id =0;
        if(isset($loan_detail_info['ListingId']))
        {
            if($loan_detail_info['PhoneValidate']==1  && $loan_detail_info['OverdueMoreCount'] == 0 && $loan_detail_info['Age']>=23)
            {
                if($loan_detail_info['CurrentRate']>=22){
                    $br=$loan_detail_info['NormalCount']/($loan_detail_info['SuccessCount']+0.001);
                    $dr = $loan_detail_info['OverdueLessCount']/($loan_detail_info['NormalCount']+0.001);
                    $bor = $loan_detail_info['OwingAmount']/($loan_detail_info['HighestDebt']+0.001);
                    $chr = $loan_detail_info['Amount']/($loan_detail_info['HighestPrincipal']+0.001);
                    if($br >=6 && $dr < 0.2 && $bor <0.5 && $chr <=2){
                        $id=$loan_detail_info['ListingId'];
                    }
                }
            }
        }
        return $id;
    }

 private function getBasicMatchLoanDetailFromDbForTest()
 {
    static $index=1;
    $m=M("history_loan");
    $data=$m->where(1)->page($index,200)->select();
    $index ++;
    echo $index . "**";
    return $data;
 }
 
 public function saveLatestHistoryLoanDetail()
 {
 	$m=M("history_loan");
 	$data=$m->where("CreditCode!='AA' && CreditCode!='AAA'")->order('UpdateTime desc')->page(1,5000)->select();
 	if(!$data){
 		if($data===false)
 			$this->ppdLog("getLatestHistoryLoanDetail DB ERROR",3);
 		return false;
 	}
 	$this->cacheSave("latestloan", $data);
    return true;
 }
 
 public function getLatestHistoryLoanDetail()
 {
    $data = $this->cacheGet("latestloan");
    return $data;
 }

private function getDiyLoanFromRedis($redis)
{
    if($redis){
        $data = $redis->rpop('QLoanDiy');
        if($data){
            return json_decode($data, true);
        }
    }
    return array();
} 

private function getSysLoanFromRedis($redis)
{
    if($redis){
        $data = $redis->rpop('QLoanSys');
        if($data){
            return json_decode($data, true);
        }
    }
    return array();
}

private function getSafeLoanFromRedis($redis)
{
    if($redis){
        $data = $redis->rpop('QSafeLoan');
        if($data){
            return json_decode($data, true);
        }
    }
    return array();
}

private  function getCreditLoanFromCache()
 {
    $data=$this->cacheGetLoan($this->credit_loan_cache);
    $d=array();
    if(!empty($data)){
        foreach ($data as $info){
            if(isset($info['ListingId']) && !$this->isIdFromDbInLatestListIdQueue($info['ListingId']))
                $d[]=$info;
        }
    }
    unset($data);
    return $d;
 }
 
private function getSafeLoanFromCache()
 {

    $data=$this->cacheGetLoan($this->safe_loan_cache);
    $d=array();
    if(!empty($data)){
        foreach ($data as $info){
            if(isset($info['ListingId']) && !$this->isIdFromDbInLatestListIdQueue($info['ListingId']))
                $d[]=$info;
        }
    }
    unset($data);
    return $d;
    
 }
 
 /* this function is obleted use getCreditLoanFromCache Instead*/
 private function getBasicMatchLoanDetailFromDb($debug=false)
 {
//      if($_SERVER['HTTP_HOST']=="localhost")
//          return $this->getBasicMatchLoanDetailFromDbForTest();
    static $last_time=0;
    if($last_time==0)
        $last_time=time()-10;
    $m=M("loan");
    $timestamp=date("Y-m-d H:i:s",$last_time-5);
    $last_time=time();
    $data=$m->where("UpdateTime>'{$timestamp}'")->select();
    $d=array();
    if(!empty($data)){
        foreach ($data as $info){
            if(isset($info['ListingId']) && !$this->isIdFromDbInLatestListIdQueue($info['ListingId']))
                $d[]=$info;
        }
//          if(count($data)>0)
//          {
//              $this->ppdLog("getBasicMatchLoanDetailFromDb loan_count:" . count($data) . "return count:". count($d));
//          }
    }

    unset($data);
    return $d;
 }
 
 private function isIdFromDbInLatestListIdQueue($list_id)
 {
    if(in_array($list_id, $this->latest_list_id_queue))
        return true;
    else {
        array_push($this->latest_list_id_queue, $list_id);
        if(count($this->latest_list_id_queue)>50)
            array_shift($this->latest_list_id_queue);
        return false;
    }
 }
 private function isIdFromPpdInLatestListIdQueue($list_id)
 {
    if(in_array($list_id, $this->latest_list_id_queue))
        return true;
    else {
        array_push($this->latest_list_id_queue, $list_id);
        if(count($this->latest_list_id_queue)>50)
            array_shift($this->latest_list_id_queue);
        return false;
    }
 }
 public function myGetDetailCallBack($response, $info, $error, $request)
 {
    $result=json_decode($response,true);
    if($result && isset($result['Result']) && $result['Result']==1 )
    {
//          $loan=M("loan");
//          $loan->addAll($result['LoanInfos'],array(),true);
//          $this->ppdLog("myGetDetailCallBack add loan count:" . json_encode($result['LoanInfos']));
            $status=$this->cacheSaveLoan($this->credit_loan_cache,$result['LoanInfos']);
            if($status===false){
                $this->ppdLog("myGetDetailCallBack cacheSaveLoan FAILD" . $response,2);
            }
    }else{
        $this->ppdLog("getLoanDetail ERR:" . $response,2);
    }
    
 }
 private function makeGetDetailRequest($loan_id_list){
    $url = "https://openapi.ppdai.com/listing/openapiNoAuth/batchListingInfo";
    $request= '{ "ListingIds": [';
    foreach ($loan_id_list as $id){
        $request=$request . $id . ',';
    }
    $request = rtrim($request, ",");
    $request= $request  . '] }';
    $req = $this->makeRequest($url, $request);
    return $req;
 }
 
 private function showApiSpeed($speed_array){
    $len=count($speed_array);
    if($len>5)
        $count=5;
    else
        $count=$len;
    $this->ppdLog(json_encode(array_slice($speed_array, 0,$count)));
    $this->ppdLog("......");
    $this->ppdLog(json_encode(array_slice($speed_array, -$count,$count)));
    $speed=0;
    $now=time();
    foreach ( $speed_array as $record){
        if($record->time>$now-60);
            $speed+=$record->count;
    }
    $this->ppdLog("********* SPEED:{$speed}/MIN *********");
    $this->ppdLog("");
    
 }
 public function showSpeed(){
    $this->updatecommand("showSpeed","AutoDownloadStatus_1");
    $this->updatecommand("showSpeed","AutoDownloadStatus_2");
    $this->updatecommand("showSpeed","AutoDownloadStatus_3");
    //$this->updatecommand("showSpeed");
 }
 public function debug(){
    $this->updatecommand("debug");
 }
 private function checkGetDetailSpeed($new_request_count)
 {
    if($new_request_count>0)
        $this->sleepOnSpeedExceedLimit($this->detail_request_list, $new_request_count, 499);
 }
 private function checkGetLoanListSpeed($new_request_count)
 {
    if($new_request_count>0)
        $this->sleepOnSpeedExceedLimit($this->loan_request_list, $new_request_count, 598);
 }
 private function checkBidSpeed($new_request_count)
 {
    if($new_request_count>0)
        $this->sleepOnSpeedExceedLimit($this->bid_request_list, $new_request_count, 2500);
 }
 
 private function sleepOnSpeedExceedLimit(&$request_list,$new_request_count,$limit){
    $cur_time=time();
    $obj = new \stdClass();
    $obj->time=$cur_time;
    $obj->count=$new_request_count;
    $request_list[]=$obj;
    $obj_time=$request_list[0]->time;
    while(!empty($request_list) && $obj_time<$cur_time-60)
    {
        $cur_obj=array_shift($request_list);
        unset($cur_obj);
        $obj_time=$request_list[0]->time;
    }
    $total_count=0;
    foreach($request_list as $cur_obj)
    {
        $total_count+=$cur_obj->count;
    }
    if($total_count>$limit)
    {
        $exceed=$total_count-$limit;
        $len=count($request_list);
        for($i=0;$i<$len;$i++){
            $exceed-=$request_list[$i]->count;
            if($exceed<0)
            {
                $sleep_time=$request_list[$i]->time+60-$cur_time+0.5;
                $this->ppdLog("API SPEED EXCEED LIMIT {$limit}. SPEED IS :$total_count, WILL SLEEP for {$sleep_time} SECONDS");
                usleep($sleep_time*1000000);
                break;
            }
        }
    }
 }
 
 private function numRequestDetailOnce($list_count)
 {
    $mode=10;
    if($list_count<=2)
        $mode=1;
    else if($list_count<=4)
        $mode=2;
    else if($list_count<=8)
        $mode=4;
    else
        $mode=10;
    return $mode;
 }
 private function storeSafeLoan($loan_list)
 {
    $m=M("safe_loan");
    $status=$m->addAll($loan_list,array(),true);
    if($status===false){
        $this->ppdLog("storeSafeLoan ERROR:" . json_encode($m->getDbError()),3);
    }
 }
 private function clearExpireSafeLoan(){
    $m=M("safe_loan");
    $timestamp=date("Y-m-d H:i:s",time()-900);
    $status=$m->where("UpdateTime<'{$timestamp}'")->delete();
    if($status===false){
        $this->ppdLog("ClearExpireSafeLoan ERROR:" . json_encode($m->getDbError()),3);
    }
 }
 /*this function is obleted. using  getSafeLoanFromCache indead*/
 private function getSafeLoanFromDb()
 {
    static $last_time=0;
    if($last_time==0)
        $last_time=time()-10;
    $m=M("safe_loan");
    $timestamp=date("Y-m-d H:i:s",$last_time-5);
    $last_time=time();
    $data=$m->where("UpdateTime>'{$timestamp}'")->select();
    $d=array();
    if(!empty($data)){
        foreach ($data as $info){
            if(isset($info['ListingId']) && !$this->isIdFromDbInLatestListIdQueue($info['ListingId']))
                $d[]=$info;
        }
    }
    unset($data);
    return $d;
 }
 private function moveCreditLoan(){
    $m=M("loan");
    $timestamp=date("Y-m-d H:i:s",time()-600);
    $data=$m->where("UpdateTime<'{$timestamp}'")->order('UpdateTime asc')->limit(100)->select();
    if($data===false){
        $this->ppdLog("moveCreditLoan SELECT ERROR:" . json_encode($m->getDbError()),3);
    }else if(!empty($data)){
        $newm=M("history_loan");
        $status=$newm->addAll($data,array(),true);
        if($status===false){
            $this->ppdLog("moveCreditLoan ADD ERROR:" . json_encode($m->getDbError()),3);
        }else{
            $status=$m->where("UpdateTime<'{$timestamp}'")->order('UpdateTime asc')->limit(100)->delete();
            if($status===false)
                $this->ppdLog("moveCreditLoan DELETE ERROR:" . json_encode($m->getDbError()),3);
        }
    }
 }
 /*this func is only for update ppdai data*/
 
 public function getDetailInfoForPPDData()
 {
    $m=M("cfig_loan");
    
 }
 private function getBasicMatchLoanDetail(){
    
        $count=0;
        $load_id_list=array();
        $loan_detail_list=array();
        $this->checkGetLoanListSpeed(1);
        $loan_basic_list = $this->getLoanList();
        $list_count=count($loan_basic_list);
//      if($list_count>0)
//          $this->ppdLog("getBasicMatchLoanDetail:list_count:$list_count" . json_encode($loan_basic_list));
        $mod=$this->numRequestDetailOnce($list_count);
        $request_list=array();
        //$safe_loan_list=array();
        foreach ( $loan_basic_list as $loan_basic_info)
        {   
            if(!$this->isIdFromPpdInLatestListIdQueue($loan_basic_info['ListingId'])){
                if ($loan_basic_info['CreditCode']=='AA' || $loan_basic_info['CreditCode']=='AAA'){
                    //array_push($safe_loan_list, $loan_basic_info);
                }else if(($list_id=$this->matchBasicStrategy($loan_basic_info))>0)
                {   
                    $loan_id_list[$count++]=$list_id;
                    if($count%$mod==0){
                        $request=$this->makeGetDetailRequest($loan_id_list);
                        array_push($request_list, $request);
                        unset ($loan_id_list);
                        $load_id_list=array();
                        $count=0;
                    }
                }
            }
        }
//      if(!empty($safe_loan_list)){
//          //$this->storeSafeLoan($safe_loan_list);
//          //$this->cacheSaveLoan($this->safe_loan_cache, $safe_loan_list);
//          //var_dump($this->safe_loan_cache);
//          //$this->ppdLog("getBasicMatchLoanDetail:safeloan count:".count($safe_loan_list));
//      }
        if($count>0){//最后的不满10个的贷款信息
            $request=$this->makeGetDetailRequest($loan_id_list);
            array_push($request_list, $request);
            unset ($loan_id_list);
        }
        $req_count=count($request_list);
//          if($req_count>0)
//          {
//              $this->ppdLog("getBasicMatchLoanDetail req_count:$req_count");
//          }
        if($req_count<400){
            $this->checkGetDetailSpeed($req_count);
            $this->requestBatchUrl($request_list, "myGetDetailCallBack");
        }else{
            $this->ppdLog(__FUNCTION__ . "A LONG REQUEST LIST IS FOUND!, WE NEED RECODE TO DEAL IT ");
        }
    }
    
    private function biddingSafeStrategy($loan_basic_list,$strategyid,&$request_array){
    
        $data['1000']=array("RateA"=>9,"RateB"=>9.5,"MonthsA"=>1,"MonthsB"=>37);
        $data['1001']=array("RateA"=>9.5,"RateB"=>10,"MonthsA"=>1,"MonthsB"=>37);
        $data['1002']=array("RateA"=>10,"RateB"=>10.5,"MonthsA"=>1,"MonthsB"=>37);
        $data['1003']=array("RateA"=>10.5,"RateB"=>11,"MonthsA"=>1,"MonthsB"=>37);
        $data['1004']=array("RateA"=>11,"RateB"=>11.5,"MonthsA"=>1,"MonthsB"=>37);
        $data['1006']=array("RateA"=>12,"RateB"=>12.5,"MonthsA"=>1,"MonthsB"=>37);
        $data['1007']=array("RateA"=>12.5,"RateB"=>13,"MonthsA"=>1,"MonthsB"=>37);
        $data['1008']=array("RateA"=>13,"RateB"=>13.5,"MonthsA"=>1,"MonthsB"=>37);
        $data['1010']=array("RateA"=>14,"RateB"=>36,"MonthsA"=>1,"MonthsB"=>37);
        
        if(!isset($data[$strategyid])){
            $this->ppdLog("safe loan strategi id :" . $strategyid ." not found</br>\n",4);
            return;
        }
    
        foreach( $loan_basic_list as $loan_basic)
        {
            if($loan_basic['CurrentRate'] >= $data[$strategyid]['RateA'] && $loan_basic['CurrentRate'] < $data[$strategyid]['RateB'])
            {
                $list_id=$loan_basic['ListingId'];
                $queue_count=count($this->bid_queue[$strategyid]);
                $max_req_num=ceil($loan_basic['Amount']/50);
                if($max_req_num>$queue_count)
                    $max_req_num=$queue_count;
                for ($i=0; $i<$max_req_num; $i++){
                    $user=array_shift($this->bid_queue[$strategyid]);
                    $request=$this->makeBidRequest($user->userid,
                            $strategyid,
                            $list_id,
                            $user->bidamount,
                            $this->user_balance[$user->userid]->accesstoken
                    );
                    $this->addbidRequest($request_array, $request);
                    $this->bid_queue[$strategyid][]=$user;
                }//end for
            }//else{//end if($this->$match($loan_detail))
        }//endforeach( $loan_detail_list as $loan_detail)
        unset($match);
    }
    

    private function biddingStrategy($loan_detail_list,$strategyid,&$request_array){
        
        $match="matchStrategy" . $strategyid;
        if(!method_exists ($this,$match)){
            $this->ppdLog("function:" . $match ." not found</br>\n",4);
            return ;
        }

        foreach( $loan_detail_list as $loan_detail)
        {   
            if($this->$match($loan_detail))
            {   
                $list_id=$loan_detail['ListingId'];
                $queue_count=count($this->bid_queue[$strategyid]);
                $max_req_num=ceil($loan_detail['Amount']/50);
                if($max_req_num>$queue_count)
                    $max_req_num=$queue_count;
                for ($i=0; $i<$max_req_num; $i++){
                    $user=array_shift($this->bid_queue[$strategyid]);
                    if ($loan_detail['CurrentRate'] >= $this->user_balance[$user->userid]->minRate
                        && $loan_detail['Months'] >= $this->user_balance[$user->userid]->minMonth
                        && $loan_detail['Months'] <= $this->user_balance[$user->userid]->maxMonth
                    ){
                        $request=$this->makeBidRequest($user->userid,
                                                       $strategyid,
                                                       $list_id,
                                                       $user->bidamount,
                                                       $this->user_balance[$user->userid]->accesstoken
                                                      );
                        $this->addbidRequest($request_array, $request);
                    }
                    $this->bid_queue[$strategyid][]=$user;
                }//end for
            }//else{//end if($this->$match($loan_detail))
        }//endforeach( $loan_detail_list as $loan_detail)
        unset($match);
    }
    
    public function start()
    {
//         $stop_time = strtotime("2017-12-23 00:00:00");
//         $start_time = strtotime("2017-12-23 05:00:00");
//         $now = time();
//         if($now >$stop_time && $now<$start_time){
//             echo "wait $stop_time $now $start_time";
//         }else{
//             $this->curlStart('Home/Autobid/startBidding?servername=Main');// 0 用户尾号为偶数的，1投用户位数为奇数的。2投所有
// //          $this->curlStart('Home/Autobid/startBidding?user_group=1');
// //             $this->curlStart('Home/Autobid/startDownload?id=1');
// //             $this->curlStart('Home/Autobid/startDownload?id=2');
// //          $this->curlStart('Home/Autobid/startDownload?id=3');
//             $this->curlStart('Home/Autobid/startNeaten');
//             $this->curlStart('Home/Autobid/startQryBid');
//             $this->curlStart('Home/Payment/run');
//             $this->curlStart('Home/Autobid/startDiyBidding?servername=Main');
//             //$this->curlStart('Home/Autobid/startDiyBiddingLevel?level=1');
//         }
    }
    
    public function startBidServer()
    {
//         $this->curlStart('Home/Autobid/startBidding?servername=BidA');// 0 用户尾号为偶数的，1投用户位数为奇数的。2投所有
//         $this->curlStart('Home/Autobid/startDiyBidding?servername=BidA');
    }
    
    public function curlStart($path)
    {
        echo "start progress in " . $_SERVER['SERVER_NAME'] . U($path) . "</br>";
        echo "<meta charset=\"UTF-8\">";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $_SERVER['SERVER_NAME'] . U($path));
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (windows NT 6.1) Applewebkit/537.17");
        $data = curl_exec($ch);
        usleep(10000);
        curl_close($ch);
        echo "Auto task started!". "</br>";
    }
    public function curlproxy()
    {
        echo "<meta charset=\"UTF-8\">";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://openapi.ppdai.com/listing/openapiNoAuth/loanList");
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (windows NT 6.1) Applewebkit/537.17");
//      curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC); //代理认证模式
//      curl_setopt($ch, CURLOPT_PROXY, "60.179.40.112"); //代理服务器地址
//      curl_setopt($ch, CURLOPT_PROXYPORT, 808); //代理服务器端口
//      //curl_setopt($ch, CURLOPT_PROXYUSERPWD, "lei:password"); //http代理认证帐号，username:password的格式
//      curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP); //使用http代理模式
        $data = curl_exec($ch);
        dump($data);
        curl_setopt($ch, CURLOPT_URL, "http://www.ppdai.com");
        dump($data);
        curl_close($ch);
        echo "curlproxy task started!". time() . "</br>";
    }
    
    public function startQryBid()
    {
        $statusKey = "QryBidStatus";
        $redis = Pedis::getBidRedis(1);
        if($redis){
            // check is there other thread alive by check heart beat
            if($redis->exists($statusKey)){
                $this->ppdLog("exit due to other alive ttl: " . $redis->ttl($statusKey) . "\n");
                return;// 如果有其他刷投标记录进程活着，则退出。
            }
            $this->ppdLog("Redis connect OK, start Qry Bid\n");
            $redis->set($statusKey,"alive");
            $redis->expire($statusKey, 60);
            
            //设置进程运行时间为一直运行
            set_time_limit(0);
            ignore_user_abort();
            $start_time=time();
            
            //如果键$statusKey 存在且值为 alive 则进行投标查询。
            while($redis->get($statusKey) == "alive" && $redis->ttl($statusKey) > 0){//forever
                $request_array = array();
                $record = $redis->rpop($this->BiddingRecordKey);
                while($record){
                    // 解析 $record JSON字符串，获取用户信息和投标信息。
                    $redis_data = json_decode($record);
                    if(!$redis_data)
                        $this->ppdLog("INVALID BID QRY RECORD: $record");
                    else{
                        //创建request 并加入request 队列
                        $request = $this->makeBidQryRequest(
                                $redis_data->UserId, 
                                $redis_data->StrategyId, 
                                $redis_data->ListId, 
                                $redis_data->Amount, 
                                $redis_data->AccessToken,
                                $redis_data->OrderId
                        );
                        $this->addbidRequest($request_array, $request);
                    }
                    $record = $redis->rpop($this->BiddingRecordKey);
                }
                //批量发送查询请求， 并用回调函数处理请求结果
                $redis->expire($statusKey, 60);
                $this->requestBatchUrl($request_array, "myQryBidCallBack");
                $redis->expire($statusKey, 60); 
                sleep(5);
            }
            $this->ppdLog("Qrybid manually  stoped\n");
        }else{
            $this->ppdLog("Redis connect failed! startQryBid abort\n",3);
        }
        
    }
    
    
    public function startNeaten()
    {
//              if($_SERVER['HTTP_HOST']=='localhost'){
//                  $this->ppdLog("startNeaten is disabled now!");
//                  echo "lstartNeaten is disabled now!</br>";
//                  return;
//              }
        $dbStatusName="NeatenStatus";
        $lock=$this->lock($dbStatusName);
        if(!$lock){
            return;
        }else{
            $status['val']='start';
            $this->storeControlStatus($status, $dbStatusName);
            $this->ppdLog("</br></br>\r\n\r\n**$dbStatusName  is restarted!**</br>\r\n");
        }
        
        set_time_limit(0);
        ignore_user_abort();
        $start_time=time();
        while($this->getControlStatus($dbStatusName)!='stop' && time() - $start_time<2400)
        {
            $start=time();
            $this->clearExpireSafeLoan();
            $this->moveCreditLoan();
            //we can move other time consuming task here
            for($i=0; $i<7; $i++){
                usleep(5000000);
                $this->updateAllUserBalance();
                usleep(5000000);
            }
            $interval = time() - $start;
            $this->ppdLog("$dbStatusName cycle:$interval");

        }
        $this->ppdLog("$dbStatusName stopped!");
        $this->unlock($lock);
    
    }
    
    public function startDownload($id=1)
    {
        ini_set('memory_limit','128M');
        $dbStatusName="AutoDownloadStatus_" . $id;
        $lock=$this->lock($dbStatusName);
        if(!$lock){
            return;
        }else{
            $status['val']='start';
            $this->storeControlStatus($status,$dbStatusName);
            $this->ppdLog("</br></br>\r\n\r\n**$dbStatusName  is restarted!**</br>\r\n");
        }

        set_time_limit(0);
        ignore_user_abort();
        session_write_close();
        $this->time_diff=$this->getTimeDiff($this->sync_server);
        $this->last_time_get_loan_list=($this->msectime()-100);
        $check_time=0;
        $n=0;
        $last_time=$this->msectime();
        $start_time=time();
        $status=$this->getControlStatus($dbStatusName);
        $cacheName="Cache_AutoDownloadSpeedControll";
        while($status!='stop' && (time()-$start_time) < (1920+$id*10))
        {
            //wait for time pice
            $stamp=$this->msectime();
            $task_status=$this->cacheGet($cacheName);
            while(isset($task_status['lasttime']) && $stamp-$task_status['lasttime']<45){
                usleep(10000);
                $stamp=$this->msectime();
                $task_status=$this->cacheGet($cacheName);
            }
            $task_status['lasttime']=$stamp;
            if(!isset($task_status['min'])){
                $task_status['min']=0;
                $task_status['speed']=0;
                $task_status['counter']=0;
            }else if(floor($stamp/60000)!=$task_status['min']){
                $task_status['min']=floor($stamp/60000);
                $task_status['speed']=$task_status['counter'];
                $task_status['counter']=0;
            }else{
                $task_status['counter']+=1;
            }
            $this->cacheSave($cacheName, $task_status);
            //start download
            $last_time=$this->msectime();
            $this->getBasicMatchLoanDetail();
            $timespan=$this->msectime()-$last_time;
            
            $check_time += $timespan;
            if($check_time>1000*60*10){
                $this->time_diff=$this->getTimeDiff($this->sync_server);
                $check_time=0;
            }
            usleep($timespan>=90?20000:(110000-$timespan*1000));
            
            if($n++%500==0)
                $this->ppdLog("$dbStatusName timespan:$timespan!");
            if($status=='showSpeed')
            {
                static $show_speed_time = 0;//controll 10s/per print
                if($show_speed_time<time()-10){ 
                    $this->ppdLog("********** show loan_request speed *********");
                    $this->showApiSpeed($this->loan_request_list);
                    $this->ppdLog("********** show detail_request speed *********");
                    $this->showApiSpeed($this->detail_request_list);
                    $this->updatecommand('start',$dbStatusName);
                    $this->updatecommand('start',$dbStatusName);
                    $show_speed_time = time();
                }
                
            }
            $status=$this->getControlStatus($dbStatusName);
        }
        $this->ppdLog("$dbStatusName stopped!");
        $this->unlock($lock);
        
    }
    
    public function startSafeLoanBidding($servername)
    {
        $statusKey = $servername . "SysSafeBidStatus";
        $loanRedisKey = "QSafeLoan";
        $redis = Pedis::getBidRedis(1);
        if($redis){
            // check is there other thread alive by check heart beat
            if($redis->exists($statusKey)){
                $this->ppdLog("exit due to other alive ttl: " . $redis->ttl($statusKey) . "\n");
                return;// 如果有其他刷投标记录进程活着，则退出。
            }
            $this->ppdLog("Redis connect OK, start SysSafeBidStatus Bid\n");
            $redis->set($statusKey,"alive");
            $redis->expire($statusKey, 60);
    
            //设置进程运行时间为一直运行
            set_time_limit(3600*4);
            ignore_user_abort();
            $start_time=time();
    
            //预备运行环境
            $this->scanStrategySetting($this->bidAppIndex);
            $this->checkAccesstoken();
            $this->checkBidQueue();
    
            //如果键$statusKey 存在且值为 alive 则进行投标查询。
            while($redis->get($statusKey) == "alive"){//forever
                $redis->expire($statusKey, 60);
                $startMsTime = $this->msectime();
                while($this->msectime() - $startMsTime < 3000){
                    $request_array = array();
                    $record = $redis->rpop($loanRedisKey);
                    while($record){
                        // 解析 $record JSON字符串，获取用户信息和投标信息。
                        $redis_data = json_decode($record, true);
                        if(!$redis_data || count($redis_data) == 0)
                            $this->ppdLog("INVALID OR EMPTY LOANs RECORD: $record");
                        else{
                            $strategy_ids=$this->getAllStrategyId();
                            $request_array=array();
                            foreach ($strategy_ids as $id){
                                if($id['StrategyId']>=1000 && $id['StrategyId']<9999 )
                                    $this->biddingSafeStrategy($redis_data, $id['StrategyId'], $request_array);
                            }
                            $request_count=count($request_array);
                            if($request_count<2000){
                                $this->checkBidSpeed($request_count);
                                $this->requestBatchUrl($request_array, "myBidCallBack");
                            }else{
                                $this->ppdLog("BID ONCE TIME EXCEED 2000, MAY BE WE NEED RECODING TO DEAL WITH THIS",3);
                            }
                        }
                        $record = $redis->rpop($loanRedisKey);
                    }
                    if(time() - $start_time > 600){
                        $this->scanStrategySetting(10);
                        $this->checkAccesstoken();
                        $this->checkBidQueue();
                        $start_time = time();
                    }else{
                        usleep(5000);
                    }
                }
            }
            $this->ppdLog("SysBid manually  stoped\n");
        }else{
            $this->ppdLog("Redis connect failed! SysBid abort\n",3);
        }
         
    }
    

    public function startBidding($servername)
    {
        $statusKey = $servername . "SysBidStatus";
        $loanRedisKey = "QLoanSys";
        $redis = Pedis::getBidRedis(1);
        if($redis){
            // check is there other thread alive by check heart beat
            if($redis->exists($statusKey)){
                $this->ppdLog("exit due to other alive ttl: " . $redis->ttl($statusKey) . "\n");
                return;// 如果有其他刷投标记录进程活着，则退出。
            }
            $this->ppdLog("Redis connect OK, start Qry Bid\n");
            $redis->set($statusKey,"alive");
            $redis->expire($statusKey, 60);
    
            //设置进程运行时间为一直运行
            set_time_limit(3600*4);
            ignore_user_abort();
            $start_time=time();
            
            //预备运行环境
            $this->scanStrategySetting($this->bidAppIndex);
            $this->checkAccesstoken();
            $this->checkBidQueue();
    
            //如果键$statusKey 存在且值为 alive 则进行投标查询。
            while($redis->get($statusKey) == "alive"){//forever
                $redis->expire($statusKey, 60);
                $startMsTime = $this->msectime();
                while($this->msectime() - $startMsTime < 3000){
                    $request_array = array();
                    $record = $redis->rpop($loanRedisKey);
                    while($record){
                        // 解析 $record JSON字符串，获取用户信息和投标信息。
                        $redis_data = json_decode($record, true);
                        if(!$redis_data || count($redis_data) == 0)
                            $this->ppdLog("INVALID OR EMPTY LOANs RECORD: $record");
                        else{
                            $strategy_ids=$this->getAllStrategyId();
                            $request_array=array();
                            foreach ($strategy_ids as $id){
                                if($id['StrategyId']>=10 && $id['StrategyId']<1000 )
                                    $this->biddingStrategy($redis_data, $id['StrategyId'], $request_array);
                            }
                            $request_count=count($request_array);
                            if($request_count<2000){
                                $this->checkBidSpeed($request_count);
                                $this->requestBatchUrl($request_array, "myBidCallBack");
                            }else{
                                $this->ppdLog("BID ONCE TIME EXCEED 2000, MAY BE WE NEED RECODING TO DEAL WITH THIS",3);
                            }
                        }
                        $record = $redis->rpop($loanRedisKey);
                    }
                    if(time() - $start_time > 600){
                        $this->scanStrategySetting(10);
                        $this->checkAccesstoken();
                        $this->checkBidQueue();
                        $start_time = time();
                    }else{
                        usleep(5000);
                    }
                }
            }
            $this->ppdLog("SysBid manually  stoped\n");
        }else{
            $this->ppdLog("Redis connect failed! SysBid abort\n",3);
        }
         
    }

    
    public function startDiyBidding($servername, $appIndex = null)
    {
        if ($appIndex == null)
            $appIndex = $this->bidAppIndex;
        $statusKey = $servername . "DiyBidStatus";
        $loanRedisKey = "QLoanDiy";
        $redis = Pedis::getBidRedis(1);
        if($redis){
            // check is there other thread alive by check heart beat
            if($redis->exists($statusKey)){
                $this->ppdLog("exit due to other alive ttl: " . $redis->ttl($statusKey) . "\n");
                return;// 如果有其他刷投标记录进程活着，则退出。
            }
            $this->ppdLog("Redis connect OK, start Qry Bid\n");
            $redis->set($statusKey,"alive");
            $redis->expire($statusKey, 60);
    
            //设置进程运行时间为一直运行
            set_time_limit(3600*4);
            ignore_user_abort();
            $start_time=time();
    
            //预备运行环境
            $last_time_get_diy_strategy=time();
            $diy_strategys=$this->getActiveDiyStrategy($appIndex);
    
            //如果键$statusKey 存在且值为 alive && timeout > 0 则进行投标查询。
            while($redis->get($statusKey) == "alive" && $redis->ttl($statusKey) > 0){//forever
                $startMsTime = $this->msectime();
                $redis->expire($statusKey, 60);
                while ($this->msectime() - $startMsTime < 3000){
                    $request_array = array();
                    $record = $redis->rpop($loanRedisKey);
                    while($record){
                        // 解析 $record JSON字符串，获取用户信息和投标信息。
                        $redis_data = json_decode($record, true);
                        if(!$redis_data || count($redis_data) == 0)
                            $this->ppdLog("INVALID OR EMPTY LOANs RECORD: $record");
                        else{
                            foreach ($redis_data as $loan){
                                if($loan['CreditCode']!="AA"){
                                    $requests=$this->matchBidReqList($loan,$diy_strategys);
                                    $request_array=array_merge($request_array,$requests);
                                }
                            }
                            $request_count=count($request_array);
                            if($request_count>0){
                                if($request_count<2000){
                                    $this->checkBidSpeed($request_count);
                                    $this->requestBatchUrl($request_array, "myBidCallBack");
                                }else{
                                    $this->ppdLog("BID ONCE TIME EXCEED 2000, MAY BE WE NEED RECODING TO DEAL WITH THIS",3);
                                }
                            }
                        }
                        $record = $redis->rpop($loanRedisKey);
                    }
                    if(time() - $last_time_get_diy_strategy > 600){
                        $diy_strategys=$this->getActiveDiyStrategy($appIndex);
                        $last_time_get_diy_strategy=time();
                    }else{
                        usleep(5000);
                    }
                }
            }
            $this->ppdLog("DiyBid manually  stoped\n");
        }else{
            $this->ppdLog("Redis connect failed! DiyBid abort\n",3);
        }
    
    }


    private function inRange($left,$right,$val)
    {
        //$this->ppdLog("left:$left,value:$val,right:$right");
        if($left==-1){
            return ($right==-1)?true:($val<=$right);
        }else {
            return ($right==-1)?$val>=$left:($val>=$left&&$val<=$right);
        }
    }

    private function MatchBidReqList($loan,&$diy_strategys)
    {
        $request_array=array();
        $max=floor($loan['Amount']/50);
        $n=0;
        //echo json_encode($loan);
        $condition_mask=$this->conditionMask($loan);
        if(!$condition_mask)
            return $request_array;
        $validate_code = $loan['CertificateValidate']|$loan['CreditValidate']<<1|$loan['EducateValidate']<<2
            |$loan['VideoValidate']<<3|$loan['PhoneValidate']<<4|$loan['NciicIdentityCheck']<<5;
        foreach ($diy_strategys as $index=>$diy){
            if(($condition_mask&$diy['ConditionMask'])==$condition_mask){
                 if(($validate_code&$diy['ValidateCode'])==$diy['ValidateCode'] && ($validate_code&$diy['ValidateCodeFalse'])==0){
                    $pass = true;
                    $pass = ($diy['TailNumber10'] > 0 && $loan['Amount']%10 == 0) || $diy['TailNumber10'] == 0;
                    $pass=$pass && $this->inRange($diy['AmountA'], $diy['AmountB'], $loan['Amount']);
                    $pass=$pass && $this->inRange($diy['MonthA'], $diy['MonthB'], $loan['Months']);
                    $pass=$pass && $this->inRange($diy['RateA'], $diy['RateB'], $loan['CurrentRate']);
                    $pass=$pass && $this->inRange($diy['SuccessCountA'], $diy['SuccessCountB'], $loan['SuccessCount']);
                    $pass=$pass && $this->inRange($diy['WasteCountA'], $diy['WasteCountB'], $loan['WasteCount']);
                    $pass=$pass && $this->inRange($diy['NormalCountA'], $diy['NormalCountB'], $loan['NormalCount']);
                    $pass=$pass && $this->inRange($diy['OverdueMoreCountA'], $diy['OverdueMoreCountB'], $loan['OverdueMoreCount']);
                    $pass=$pass && $this->inRange($diy['OverdueCountA'], $diy['OverdueCountB'], $loan['OverdueLessCount']);
                    $pass=$pass && $this->inRange($diy['NormalSuccessRatioA'], $diy['NormalSuccessRatioB'], $loan['NormalCount']/($loan['SuccessCount']+0.0001));
                    $pass=$pass && $this->inRange($diy['DelayNormalRatioA'], $diy['DelayNormalRatioB'], $loan['OverdueLessCount']/($loan['NormalCount']+0.01));
                    $pass=$pass && $this->inRange($diy['OwingAmountA'], $diy['OwingAmountB'], $loan['OwingAmount']);
                    $pass=$pass && $this->inRange($diy['OwingPrevHighDebtRatioA'], $diy['OwingPrevHighDebtRatioB'], $loan['OwingAmount']/($loan['HighestDebt']+0.0001));
                    $pass=$pass && $this->inRange($diy['OwingHighDebtRatioA'], $diy['OwingHighDebtRatioB'], ($loan['OwingAmount']+$loan['Amount'])/($loan['HighestDebt']+0.0001));
                    $pass=$pass && $this->inRange($diy['LastHighestBorrowRatioA'], $diy['LastHighestBorrowRatioB'], $loan['Amount']/($loan['HighestPrincipal']+0.0001));
                    $pass=$pass && $this->inRange($diy['LastSuccessIntervalA'], $diy['LastSuccessIntervalB'], $this->strToPassedDays($loan['LastSuccessBorrowTime']));
                    $pass=$pass && $this->inRange($diy['FirstSuccessIntervalA'], $diy['FirstSuccessIntervalB'],$this->strToPassedMonths($loan['FirstSuccessBorrowTime']));
                    
                    //*20180915新加*/
                    $pass=$pass && $this->inRange($diy['AgeRangeA'], $diy['AgeRangeB'],$loan['Age']);
                    $pass=$pass && $this->inRange($diy['AvgBorrowIntervalA'], $diy['AvgBorrowIntervalB'], $loan['SuccessCount'] > 0? ($this->strToPassedDays($loan['FirstSuccessBorrowTime'])/$loan['SuccessCount']): 0);
                    $pass=$pass && $this->inRange($diy['CurAvgIntervalRatioA'], $diy['CurAvgIntervalRatioB'], $loan['SuccessCount'] > 0? ($this->strToPassedDays($loan['LastSuccessBorrowTime'])/$this->strToPassedDays($loan['FirstSuccessBorrowTime'])*$loan['SuccessCount']): 0);
                    $pass=$pass && $this->inRange($diy['RegisterFirstIntervalA'], $diy['RegisterFirstIntervalB'], $this->strToPassedMonths($loan['RegisterTime']) - $this->strToPassedMonths($loan['FirstSuccessBorrowTime']));
                    $pass=$pass && $this->inRange($diy['RegisterMonthA'], $diy['RegisterMonthB'], $this->strToPassedMonths($loan['RegisterTime']));
                    $pass=$pass && $this->inRange($diy['OwingAfterAmountA'], $diy['OwingAfterAmountB'], $loan['OwingAmount'] + $loan['Amount']);
                    $pass=$pass && $this->inRange($diy['TotalBorrowA'], $diy['TotalBorrowB'], $loan['TotalPrincipal']);
                    $pass=$pass && $this->inRange($diy['OwnPreTotalBorrowRatioA'], $diy['OwnPreTotalBorrowRatioB'], $loan['OwingAmount']/($loan['TotalPrincipal'] + 0.0001));
                    $pass=$pass && $this->inRange($diy['CurAmountTotalBorrowRatioA'], $diy['CurAmountTotalBorrowRatioB'], $loan['Amount']/($loan['TotalPrincipal'] + 0.0001));
                    $pass=$pass && $this->inRange($diy['AvgBorrowAmountA'], $diy['AvgBorrowAmountB'], $loan['TotalPrincipal']/($loan['SuccessCount'] + 0.0001));
                    $pass=$pass && $this->inRange($diy['CurAmountAvgBorrowRatioA'], $diy['CurAmountAvgBorrowRatioB'], $loan['Amount']/($loan['TotalPrincipal'] + 0.0001)*$loan['SuccessCount']);
                    
                    //*20181127新加*//
                    $pass = $pass && $this->inRange($diy['HighDebtA'], $diy['HighDebtB'], $loan['HighestDebt']);
                    $pass = $pass && $this->inRange($diy['OwingAmountRatioA'], $diy['OwingAmountRatioB'], $loan['OwingAmount']/$loan['Amount']);
                    $pass = $pass && $this->inRange($diy['WasteNormalRatioA'], $diy['WasteNormalRatioB'], $loan['WasteCount']/($loan['NormalCount'] + 0.0001));
                    $pass = $pass && $this->inRange($diy['CancelCountA'], $diy['CancelCountB'], $loan['CancelCount']);
                    $pass = $pass && $this->inRange($diy['FailCountA'], $diy['FailCountB'], $loan['FailedCount']);
                    
                    
                    
                    $time=time() + 28800;//time zone adjust
                    $last_time = strtotime($diy['LastBidTime']) + 28800;
                    $last_bid_one_day_ago=(floor($time/86400)>floor($last_time/86400));
                    //$this->ppdLog("last_bid_one_day_ago:$last_bid_one_day_ago :time:$time :". floor($time%86400/3600) . ":last_time:$last_time:" . floor($last_time%86400/3600));
                    if($pass && ($diy['DayAmountLimit']==0||$last_bid_one_day_ago||($diy['DayAmount']+$diy['BidAmount'])<$diy['DayAmountLimit'])){
                        //echo json_encode($loan);          
                        //echo "</br>";
                        //$this->biddingRecord($diy['UserId'],  $loan['ListingId'], $diy['StrategyId'], $diy['BidAmount']);
                        $req=$this->makeBidRequest($diy['UserId'], $diy['StrategyId'], $loan['ListingId'], $diy['BidAmount'], $diy['AccessToken']);
                        //$this->ppdLog(json_encode($req));
                        $this->addbidRequest($request_array, $req);
                        unset($diy_strategys[$index]);
                        array_push($diy_strategys, $diy);
                        $n++;
                        if($n>=$max)
                            break;
                    }
                }else{
                    //$this->ppdLog("validate_code mask failed: " . dechex($validate_code) ."," . dechex($diy['ValidateCode']));
                }
            }else{
                //$this->ppdLog("condition mask failed: " . dechex($condition_mask). "," . dechex($diy['ConditionMask']));
            }
        }
        return $request_array;

    }
    
    
    public function getMatchedloans($loans,$diy)
    {
        $new_loans=array();
        foreach ($loans as $loan){
            $condition_mask=$this->conditionMask($loan);
            if(!$condition_mask)
                continue;
            $validate_code=$loan['CertificateValidate']|$loan['CreditValidate']<<1|$loan['PhoneValidate']<<4|$loan['NciicIdentityCheck']<<5;
            if(($condition_mask&$diy['ConditionMask'])==$condition_mask){
                //必备认证 都具备，且排除认证都没有。则继续。
                if(($validate_code&$diy['ValidateCode'])==$diy['ValidateCode'] && ($validate_code&$diy['ValidateCodeFalse'])==0){
                    $pass=true;
                    $pass = ($diy['TailNumber10'] > 0 && $loan['Amount']%10 == 0) || $diy['TailNumber10'] == 0;
                    $pass=$pass && $this->inRange($diy['AmountA'], $diy['AmountB'], $loan['Amount']);
                    $pass=$pass && $this->inRange($diy['MonthA'], $diy['MonthB'], $loan['Months']);
                    $pass=$pass && $this->inRange($diy['RateA'], $diy['RateB'], $loan['CurrentRate']);
                    $pass=$pass && $this->inRange($diy['SuccessCountA'], $diy['SuccessCountB'], $loan['SuccessCount']);
                    $pass=$pass && $this->inRange($diy['WasteCountA'], $diy['WasteCountB'], $loan['WasteCount']);
                    $pass=$pass && $this->inRange($diy['NormalCountA'], $diy['NormalCountB'], $loan['NormalCount']);
                    $pass=$pass && $this->inRange($diy['OverdueMoreCountA'], $diy['OverdueMoreCountB'], $loan['OverdueMoreCount']);
                    $pass=$pass && $this->inRange($diy['OverdueCountA'], $diy['OverdueCountB'], $loan['OverdueLessCount']);
                    $pass=$pass && $this->inRange($diy['NormalSuccessRatioA'], $diy['NormalSuccessRatioB'], $loan['NormalCount']/($loan['SuccessCount']+0.0001));
                    $pass=$pass && $this->inRange($diy['DelayNormalRatioA'], $diy['DelayNormalRatioB'], ($loan['OverdueLessCount']+$loan['OverdueMoreCount'])/($loan['NormalCount'])+0.0001);
                    $pass=$pass && $this->inRange($diy['OwingAmountA'], $diy['OwingAmountB'], $loan['OwingAmount']);
                    $pass=$pass && $this->inRange($diy['OwingPrevHighDebtRatioA'], $diy['OwingPrevHighDebtRatioB'], $loan['OwingAmount']/($loan['HighestDebt']+0.0001));
                    $pass=$pass && $this->inRange($diy['OwingHighDebtRatioA'], $diy['OwingHighDebtRatioB'], ($loan['OwingAmount']+$loan['Amount'])/($loan['HighestDebt']+0.0001));
                    $pass=$pass && $this->inRange($diy['LastHighestBorrowRatioA'], $diy['LastHighestBorrowRatioB'], $loan['Amount']/($loan['HighestPrincipal']+0.0001));
                    $pass=$pass && $this->inRange($diy['LastSuccessIntervalA'], $diy['LastSuccessIntervalB'], $this->strToPassedDays($loan['LastSuccessBorrowTime']));
                    $pass=$pass && $this->inRange($diy['FirstSuccessIntervalA'], $diy['FirstSuccessIntervalB'],$this->strToPassedMonths($loan['FirstSuccessBorrowTime']));
                    //*20180915新加*/
                    $pass=$pass && $this->inRange($diy['AgeRangeA'], $diy['AgeRangeB'],$loan['Age']);
                    $pass=$pass && $this->inRange($diy['AvgBorrowIntervalA'], $diy['AvgBorrowIntervalB'], $loan['SuccessCount'] > 0? ($this->strToPassedDays($loan['FirstSuccessBorrowTime'])/$loan['SuccessCount']): 0);
                    $pass=$pass && $this->inRange($diy['CurAvgIntervalRatioA'], $diy['CurAvgIntervalRatioB'], $loan['SuccessCount'] > 0? ($this->strToPassedDays($loan['LastSuccessBorrowTime'])/$this->strToPassedDays($loan['FirstSuccessBorrowTime'])*$loan['SuccessCount']): 0);
                    $pass=$pass && $this->inRange($diy['RegisterFirstIntervalA'], $diy['RegisterFirstIntervalB'], $this->strToPassedDays($loan['RegisterTime']) - $this->strToPassedDays($loan['FirstSuccessBorrowTime']));
                    $pass=$pass && $this->inRange($diy['RegisterMonthA'], $diy['RegisterMonthB'], $this->strToPassedMonths($loan['RegisterTime']));
                    $pass=$pass && $this->inRange($diy['OwingAfterAmountA'], $diy['OwingAfterAmountB'], $loan['OwingAmount'] + $loan['Amount']);
                    $pass=$pass && $this->inRange($diy['TotalBorrowA'], $diy['TotalBorrowB'], $loan['TotalPrincipal']);
                    $pass=$pass && $this->inRange($diy['OwnPreTotalBorrowRatioA'], $diy['OwnPreTotalBorrowRatioB'], $loan['OwingAmount']/($loan['TotalPrincipal'] + 0.0001));
                    $pass=$pass && $this->inRange($diy['CurAmountTotalBorrowRatioA'], $diy['CurAmountTotalBorrowRatioB'], $loan['Amount']/($loan['TotalPrincipal'] + 0.0001));
                    $pass=$pass && $this->inRange($diy['AvgBorrowAmountA'], $diy['AvgBorrowAmountB'], $loan['TotalPrincipal']/($loan['SuccessCount'] + 0.0001));
                    $pass=$pass && $this->inRange($diy['CurAmountAvgBorrowRatioA'], $diy['CurAmountAvgBorrowRatioB'], $loan['Amount']/($loan['TotalPrincipal'] + 0.0001)*$loan['SuccessCount']);

                    //*20181127新加*//
                    $pass = $pass && $this->inRange($diy['HighDebtA'], $diy['HighDebtB'], $loan['HighestDebt']);
                    $pass = $pass && $this->inRange($diy['OwingAmountRatioA'], $diy['OwingAmountRatioB'], $loan['OwingAmount']/$loan['Amount']);
                    $pass = $pass && $this->inRange($diy['WasteNormalRatioA'], $diy['WasteNormalRatioB'], $loan['WasteCount']/($loan['NormalCount'] + 0.0001));
                    $pass = $pass && $this->inRange($diy['CancelCountA'], $diy['CancelCountB'], $loan['CancelCount']);
                    $pass = $pass && $this->inRange($diy['FailCountA'], $diy['FailCountB'], $loan['FailedCount']);

                    if($pass){
                        array_push($new_loans, $loan);
                    }else{
                        //$this->ppdLog("pass check failed:$pass");
                    }
                }else{
                    //$this->ppdLog("validate_code mask failed: " . dechex($validate_code) ."," . dechex($diy['ValidateCode']));
                }
            }else{
                //$this->ppdLog("condition mask failed: " . dechex($condition_mask). "," . dechex($diy['ConditionMask']));
            }
        }
        return $new_loans;
    
    }
    
    private function conditionMask($loan)
    {
        $condition_mask=0;
        $credit=$this->creditCodeMask($loan);
        $degree=$this->educationDegreeMask($loan);
        $studystyle=$this->studyStyleMask($loan);
        $school=$this->graduateSchoolMask($loan);
        $gender=$this->genderMask($loan);
        $age=$this->ageMask($loan);
        if(!$credit||!$degree||!$studystyle||!$school||!$gender||!$age)
            return 0;
        else{
            $condition_mask|=$credit;
            //$this->ppdLog(  "     ::" . $loan['ListingId'] ."credit:" .dechex((int)$credit). "condition_mask " . dechex((int)$condition_mask) . "</br>");
            $condition_mask|=$degree<<7;
            //$this->ppdLog( "        ::" . $loan['ListingId'] . $loan['EducationDegree'] ."degree".dechex((int)$degree). "condition_mask " . dechex((int)$condition_mask) . "</br>");
            $condition_mask|=$studystyle<<13;
            //$this->ppdLog("        ::" . $loan['ListingId'] . $loan['StudyStyle'] . ":studystyle : " .dechex($studystyle). "condition_mask " . dechex($condition_mask) . "</br>");

            $condition_mask|=$school<<19;
            //echo "        school : " .dechex($school). "condition_mask " . dechex($condition_mask) . "</br>";
            $condition_mask|=$gender<<23;
            //echo "        gender : " .dechex($gender). "condition_mask " . dechex($condition_mask) . "</br>";
            $condition_mask|=$age<<25;
            //$this->ppdLog("        ::" . $loan['ListingId'] . $loan['StudyStyle'] . "age:" .dechex($age). "condition_mask " . dechex($condition_mask) . "</br>");
            return $condition_mask;
        }
        
    }
    private function creditCodeMask($loan)
    {
        $condition_mask=0;
        if($loan['CreditCode']=='AA'){
            $condition_mask=1;
        }else if($loan['CreditCode']=='A'){
            $condition_mask=2;
        }else if($loan['CreditCode']=='B'){
            $condition_mask=4;
        }else if($loan['CreditCode']=='C'){
            $condition_mask=8;
        }else if($loan['CreditCode']=='D'){
            $condition_mask=16;
        }else if($loan['CreditCode']=='E'){
            $condition_mask=32;
        }else if($loan['CreditCode']=='F'){
            $condition_mask=64;
        }
        return intval($condition_mask);
    }
    private function educationDegreeMask($loan)
    {
        $condition_mask=0;
        if($loan['EducationDegree']=='博士研究生'){
            $condition_mask=1;
        }else if($loan['EducationDegree']=='硕士研究生'){
            $condition_mask=2;
        }else if($loan['EducationDegree']=='本科' || $loan['EducationDegree']=='专升本'){
            $condition_mask=4;
        }else if($loan['EducationDegree']=='专科'){
            $condition_mask=8;
        }else if($loan['EducationDegree']=='专科(高职)'){
            $condition_mask=16;
        }else if($loan['EducationDegree']==null){
            $condition_mask=32;
        }
        return intval($condition_mask);
    }
    
    private function studyStyleMask($loan)
    {
        $condition_mask=0;
        if($loan['StudyStyle']=='普通'||$loan['StudyStyle']=='普通全日制'||$loan['StudyStyle']=='全日制'){
            $condition_mask=1;
        }else if($loan['StudyStyle']=='成人'){
            $condition_mask=2;
        }else if($loan['StudyStyle']=='研究生'){
            $condition_mask=4;
        }else if($loan['StudyStyle']=='网络教育'){
            $condition_mask=8;
        }else if($loan['StudyStyle']=='自学考试'||$loan['StudyStyle']=='自考'){
            $condition_mask=16;
        }else {
            $condition_mask=32;
        }
        return $condition_mask;
    }
    private function graduateSchoolMask($loan)
    {
        $condition_mask=0;
        if($this->isGraduateFrom985($loan['GraduateSchool'])){
            $condition_mask|=1;
        }else if($this->isGraduateFrom211($loan['GraduateSchool'])){
            $condition_mask|=1<<1;
        }else if($loan['GraduateSchool']!=null){
            $condition_mask|=1<<2;
        }else{
            $condition_mask|=1<<3;
        }
        
        return $condition_mask;
    }
    
    private function genderMask($loan)
    {
        $condition_mask=0;
        if($loan['Gender']==1){
            $condition_mask|=1;
        }else 
            $condition_mask|=1<<1;
        return $condition_mask;
    }
    
    private function ageMask($loan)
    {
        $condition_mask=0;
        if($loan['Age']>=18&&$loan['Age']<=22){
            $condition_mask|=1;
        }else if($loan['Age']>=23&&$loan['Age']<=27){
            $condition_mask|=1<<1;
        }else if($loan['Age']>=28&&$loan['Age']<=32){
            $condition_mask|=1<<2;
        }else if($loan['Age']>=33&&$loan['Age']<=40){
            $condition_mask|=1<<3;
        }else if($loan['Age']>=41&&$loan['Age']<=60){
            $condition_mask|=1<<4;
        }
    
        return $condition_mask;
    }
    private function storeLoanDetail($loan_detail_stored)
    {
        foreach($loan_detail_stored as $id=>$loan)
        {
            if(isset($loan['timestamp']))
                unset($loan_detail_stored[$id]['timestamp']);
        }
        $loan=M("loan");
        $loan->addAll($loan_detail_stored,array(),true);
    }
    
    private function addDiyDailyAmount($strategyid,$user_id,$bidamount,$time){
        $m=M("personal_strategy");
        $data=$m->where("StrategyId='{$strategyid}' AND UserId='{$user_id}'")->find();
        if($data){
            $last_time=strtotime($data['LastBidTime']);
            $data['LastBidTime']=date("Y-m-d H:i:s",$time);
            
            if(floor(($time+28800)/86400)>floor(($last_time+28800)/86400))
            {
                $data['DayAmount']=$bidamount;
            }else{
                $data['DayAmount']=$bidamount+$data['DayAmount'];
            }
            $status=$m->data($data)->save();
            if($status===false)
                $this->ppdLog("addDiyDailyAmount DB erro",3);
        }else if($data===false)
                $this->ppdLog("addDiyDailyAmount DB erro",3);
    }
    
    private  function biddingRecord($user_id,$list_id,$strategyid, $bidamount, $appIndex = 0)
    {
        $time=$this->syncTime($this->time_diff);
        $this->addDiyDailyAmount($strategyid, $user_id, $bidamount, $time);
        $this->ppdLog("AppIndex: $appIndex BID Record: USER:$user_id,LIST_ID:$list_id,STRATEGY_ID:$strategyid; TIME:" . date("Y-m-d H:i:s",$time));
        $r=M("bid");
        $data['UserId']=$user_id;
        $data['ListingId']=$list_id;
        $data['StrategyId']=$strategyid;
        $data['BidAmount']=$bidamount;
        $data['BidTime']=date("Y-m-d H:i:s",$time);
        $data['BidSN']="$user_id$list_id$strategyid";
        $data['AppIndex']=$appIndex;
        if($strategyid >= 1000)
            $bidcost=$bidamount*$this->cost_rate;
        else
            $bidcost=$bidamount*$this->cost_rate_sys;
        $data['BidCost']=$bidcost;
        $find=$r->where("BidSN='{$data['BidSN']}'")->find();
        if($find){
            $this->ppdLog("bidrecord-repeated; user,$user_id,list_id:$list_id,strategyid:$strategyid;",1);
            return;
        }
            
        if(!$r->add($data))
            $this->ppdLog("biddingRecord add data failed!\n last database err is :" . $r->getDbError(),3);
        if($bidcost>0){
            $user=M("User");
            $status=$user->where("UserId='{$user_id}'")->setDec("Score",$bidcost);
            if($status===false){
                $this->ppdLog("biddingRecord Charge failed  \ndata:". json_encode($data) . "\nlast database err is :" . $user->getDbError(),3);
            }
        }
        $this->bid_count++;
        //maybe need clear cache if isbidded using cache;
    }
    private function isBidded($list_id,$user_id,$strategyid){
        $m=M("bid");
        $condition['UserId']=$user_id;
        $condition['ListingId']=$list_id;
        $data=$m->where($condition)->find();
        if(isset($data['UserId']) && $data['UserId']== $user_id)
            return true;
        else 
            return false;
        // add cache to improve performance;
    }
    private function getAllStrategyId()
    {
        $strategy=M("Strategy");
        $ids=$strategy->where('status=0')->field('StrategyId')->order('ExpectRate desc')->cache(true,60)->select();
        return $ids;
    
    }
    private function getSafeStrategyId()
    {
        $strategy=M("Strategy");
        $ids=$strategy->where("StrategyId<10 OR StrategyId>=1000")->field('StrategyId')->order('ExpectRate desc')->cache(true,60)->select();
        return $ids;
    
    }
    public function testGetDiy($level)
    {
        dump($this->getActiveDiyStrategy($level));
    }
    private function getActiveDiyStrategy($appIndex){
        $minBalace_hash = array();
        $mulAuth_hash = array();
        $strategy=M("personal_strategy");
        $diys=$strategy->where("BidAmount>=50")->cache(true,60)->select();
        if($diys){
            $user=M("user");
            $deadtime=date("Y-m-d H:i:s",time()+3600);
            $data=$user->where("ATExpireDate>= 0 AND ATExpireDate>'{$deadtime}' AND UserBalance>=50 AND Score>1000")->cache(true,2)->select();
            $ugsm = M("user_global_setting");
            $global_setting = $ugsm->where(1)->select();
            if($global_setting === false){
                $this->ppdLog("global_setting not found!",3);
            }else{
                foreach ($global_setting as $gs){
                    $minBalace_hash[$gs['UserId']] = $gs['MinBalance'];
                }
            }
            /*获取多授权*/
            $mul = M("user_multi_auth");
            $mulAuth = $mul->where("Status = 0 and AppIndex = '{$appIndex}'")->select();
            if($mulAuth === false){
                $this->ppdLog("MultiAuth DB ERROR");
            }else{
                foreach($mulAuth as $auth){
                    $mulAuth_hash[$auth['UserId']] = $auth['AccessToken'];
                }
            }


            if($data===false){
                $this->ppdLog("getActiveDiyStrategy DB ERROR",3);
                $diys=array();
            }else if($data==null){
                $diys=array();
            }else{
                foreach( $diys as  $id=>$diy){
                    $find=false;
                    foreach( $data as $userinfo){
                        if($diy['UserId']==$userinfo['UserId'] && $userinfo['UserBalance'] > $minBalace_hash[$userinfo['UserId']]){
                            if(isset($mulAuth_hash[$userinfo['UserId']])){
                                $diys[$id]['AccessToken']=$mulAuth_hash[$userinfo['UserId']];
                                $find=true;
                                break;
                            }
                        }
                    }
                    if(!$find)
                        unset($diys[$id]);
                }
                // to do remove balance lt minBalance setting.
            }
            
        }else if($diys===false){
            $this->ppdLog("getActiveDiyStrategy DB ERROR",3);
            $diys=array();
        }else 
            $diys=array();
        
        shuffle($diys);
        return $diys;
    }

    public function myBidCallBack($response, $info, $error, $request)
    {
    //$this->ppdLog("myBidCallBack res:{$response} req: {$request}");
        $data=json_decode($response,true);
        if(!isset($data['Result'])){
            $this->ppdLog("bidcallback response parsh error.UserId:" . $request->user_id. ",response:{$response}",2);
            if(isset($data['Code']) && $data['Code']=="GTW-BRQ-INVALIDTOKEN" && isset($request->user_id) && isset($data['HttpStatus'])){
                $this->dealWithTokenError(-$data['HttpStatus'], $request->user_id);
            }else if(isset($data['Code']) && $data['Code']=="GTW-BRQ-FREQUENTLY")
            {
                sleep(1);
            }
            
            //$data['Result']=$this->bidding($request->list_id, $request->amount,$request->accesstoken);//do not retry.
        }else if(isset($data['Result'])){
            $bidstatus = $data['Result'];
            $user_id = $request->user_id;
            $strategy_id = $request->strategy_id;
            $list_id = $request->list_id;
            $amount = $request->amount;
            $token = $request->accesstoken;
            
            if (($bidstatus == 9999 || $bidstatus == 0) && isset($data['OrderId'])) {
//                 if(isset($data['ParticipationAmount']))
//                     $bid_amount=$data['ParticipationAmount'];
//                 else{
//                     $bid_amount=$amount;
//                     $this->ppdLog("ParticipationAmount not found with is wired!",3);
//                 }
                $redis = Pedis::getBidRedis(0.5);
                if($redis)
                {
                    $redis_data = array();
                    $redis_data['ListId'] = $list_id;
                    $redis_data['Amount'] = $amount;
                    $redis_data['OrderId'] = $data['OrderId'];
                    $redis_data['UserId'] = $user_id;
                    $redis_data['StrategyId'] = $strategy_id;
                    $redis_data['AccessToken'] = $token;
                    $redis->lpush($this->BiddingRecordKey, json_encode($redis_data));
                }else{
                    $this->ppdLog("$redis is null. bid info lost:" . json_encode($redis_data), 3);
                }
                if($strategy_id < 100000){
                    $this->user_balance[$user_id]->balance -= $amount;
                    if(time()>$this->charge_start_time)
                        $this->user_balance[$user_id]->Score -= $amount*20*2;
                }
                
            }else if ($bidstatus == 4001){
                if(isset($this->user_balance[$user_id])){
                    $this->user_balance[$user_id]->balance=0;
                    $this->ppdLog("User:$user_id balance not enough strategy_id:$strategy_id code:$bidstatus,bidamount:" . $amount . "mem balance:" . $this->user_balance[$user_id]->balance,1);
                }
            }else if ($bidstatus == 2001 || $bidstatus == 2002 || $bidstatus == 3005){
                $time=$this->syncTime($this->time_diff);
                $this->ppdLog("LOAN BID Abnormal USER:" . $user_id. " LIST_ID: {$list_id} STRATEGY:" .$strategy_id. " ERROR CODE: $bidstatus, TIMESTAMP: " . date("Y-m-d H:i:s",$time),3);
            }else{
                $this->ppdLog("LOAN BID ERR USER:" . $request->user_id . "STRATEGY: $strategy_id code:$bidstatus,responce:$response",3);
            }
            if(time()>$this->charge_start_time)
                $least_score=$amount*20*2;
            else
                $least_score=0;
            if(isset($this->user_balance[$user_id]) && isset($this->bid_queue[$strategy_id]) && ($this->user_balance[$user_id]->balance<$amount||$this->user_balance[$user_id]->Score<$least_score))//如果资金不够
            {
                foreach ($this->bid_queue[$strategy_id] as $id=>$user){
                    if($user->userid==$user_id){
                        unset($this->bid_queue[$strategy_id][$id]);
                        $this->wait_queue[$strategy_id][]=$user;
                        $this->ppdLog($user_id. "MOVE FROM BID QUEUE TO WAIT QUEUE,balance:".  $this->user_balance[$user_id]->balance . "bidamount:" . $amount,1);
                        unset($user);
                        break;
                    }
                }
            }//end if($this
            
        }
        
    }
    
    public function myQryBidCallBack($response, $info, $error, $request)
    {
        $redis_data = array();
        $redis_data['ListId'] = $request->list_id;
        $redis_data['Amount'] = $request->amount;
        $redis_data['OrderId'] = $request->order_id;
        $redis_data['UserId'] = $request->user_id;
        $redis_data['StrategyId'] = $request->strategy_id;
        $redis_data['AccessToken'] = $request->accesstoken;
        $data=json_decode($response,true);
        if(!isset($data['result'])){
            $redis = Pedis::getBidRedis(0.5);
            if ($redis){
                $redis->lpush($this->BiddingRecordKey, json_encode($redis_data));
            }
            $this->ppdLog("myQryBidCallBack response parsh error.UserId:" . $request->user_id. ",response:{$response}",2);
            $this->ppdLog(" redis data : ". json_encode($redis_data));
        }else if(1 == $data['result']){//投标成功
            if(isset($data['resultContent']))
                $bidamount = $data['resultContent']['participationAmount'];
            else {
                $bidamount = $request->amount;
                $this->ppdLog("participationAmount not found which is wired! response:". $response);
            }
            $this->biddingRecord($request->user_id, $request->list_id, $request->strategy_id, $bidamount, $this->bidAppIndex);
        }else if(2 == $data['result']){//投标处理中
            $redis = Pedis::getBidRedis(0.5);
            if ($redis){
                $redis->lpush($this->BiddingRecordKey, json_encode($redis_data));
            //$this->ppdLog("OrderId:" . $request->order_id . " is dealing, write back to redis: " . json_encode($redis_data));
            }else{
                $this->ppdLog("ERR: REDIS CONNECT FAILED: BID RECORD MAY LOST:" . json_encode($redis_data));
            }
        }else if(3 == $data['result']){
            $this->ppdLog("myQryBidCallBack" . $data['resultMessage'] . "request:" . json_encode($redis_data));
        }else{
            $this->ppdLog("myQryBidCallBack Result parse error: response:" . $response . "request:" . json_encode($redis_data));
        }
    }
    
    private function requestBatchUrl($request_array,$callback){
        if(!empty($request_array)){
            $curl = new \Home\Controller\CurlController ($this,$callback);
            foreach ($request_array as $request) {
                $curl->add($request);
            }
            session_write_close();//close session so we can request curl to self
            $curl->execute();
            echo $curl->display_errors();
        }
    }
    
    private function addbidRequest(&$request_array,$request){
        foreach($request_array as $id=>$cur_request)
        {
            if($cur_request->user_id==$request->user_id && $cur_request->list_id==$request->list_id)
                return;
        }
        //if(!$this->isBidded($request->list_id, $request->user_id,$request->strategy_id))
                array_push($request_array, $request);
    }


    /* 投标API 添加投标用户和策略信息，方便回调处理。*/
    public function makeBidRequest($user_id,$strategy_id,$list_id,$amount,$accesstoken)
    {
        $url = "https://openapi.ppdai.com/listing/openapi/bid";
        $request = '{ "ListingId": '. $list_id . ',"Amount": ' . $amount . ',"UseCoupon":"true" }';
        $req = $this->makeRequest($url, $request,$accesstoken, $this->bidAppId, $this->bidAppPrivateKey);
        $req->user_id = $user_id;
        $req->list_id = $list_id;
        $req->amount = $amount;
        $req->accesstoken = $accesstoken;
        $req->strategy_id = $strategy_id;
        return $req;
    
    }
    
    
    public function makeBidQryRequest($user_id, $strategy_id, $list_id, $amount, $accesstoken, $order_id)
    {
        $url = "https://openapi.ppdai.com/listingbid/openapi/queryBid";
        $request = "{ \"orderId\": \"$order_id\", \"listingId\": \"$list_id\" }";
        $req = $this->makeRequest($url, $request,$accesstoken, $this->bidAppId, $this->bidAppPrivateKey);
        $req->user_id = $user_id;
        $req->list_id = $list_id;
        $req->amount = $amount;
        $req->order_id = $order_id;
        $req->accesstoken = $accesstoken;
        $req->strategy_id = $strategy_id;
        return $req;
    
    }
    
    private function makeRequest($url, $request, $accesstoken = '', $appid = null, $privatekey = null)
    {
        if($appid == null)
            $appid = $this->appid;
        $timestamp = gmdate ( "Y-m-d H:i:s", $this->syncTime($this->timediff)); // UTC format
        $timestap_sign = $this->sign($appid. $timestamp, $privatekey);
        $requestSignStr = $this->sortToSign($request);
        $request_sign = $this->sign($requestSignStr, $privatekey);
    
        $header = array ();
        $header [] = 'Content-Type:application/json;charset=UTF-8';
        $header [] = 'X-PPD-TIMESTAMP:' . $timestamp;
        $header [] = 'X-PPD-TIMESTAMP-SIGN:' . $timestap_sign;
        $header [] = 'X-PPD-APPID:' . $appid;
        $header [] = 'X-PPD-SIGN:' . $request_sign;
        $header [] = 'X-PPD-ACCESSTOKEN:' . $accesstoken;
    
        $option = array(
                CURLOPT_RETURNTRANSFER => '1',
        );
        $req = new \Home\Controller\CurlRequestController ($url, 'POST',$request, $header, $option);
        return $req;
    }
    
//     private function bidding($list_id,$amount,$accesstoken) {
//         // 0 normal
// //              -1  未知异常    重新请求接口或联系开放平台维护人员
// //      1002    用户信息不存在  请重新让用户授权，或联系开放平台维护人员
// //      1001    用户编号异常    联系开放平台维护人员
// //      2001    标的编号异常    请重新输入正确列表编号
// //      2002    标的不存在  请重新输入正确列表编号或尝试更换其他列表编号
// //      3001    单笔投标金额只能是50-10000的整数    请重新输入投标金额
// //      3002    累计投标金额不能＞20000元   请重新输入投标金额
// //      3003    累计投标金额不能＞标的金额的30% 请重新输入投标金额
// //      3004    不能给自己投标  请重新输入列表编号
// //      3005    已满标  请重新输入列表编号
// //      4001    账户余额不足，请先充值  请充值或更换投标账号
// //      $this->ppdLog("</br>bidding,id:" . $list_id . "amount:" . $amount . "</br>");
// //      return 0;
//         //投标
//         $url = "https://openapi.ppdai.com/invest/BidService/Bidding";
//         $request = '{ "ListingId": '. $list_id . ',"Amount": ' . $amount . ' }';
//         $result = $this->send($url, $request,$accesstoken);
//         if( isset($result['Result']) )
//             $status=$result['Result'];
//         else
//             $status=-1; //未知异常
// //      if($status!=0)
// //          $this->ppdLog("bidding error! id:" . $list_id . "errcode" . $status);
//         return $status;
//     }
    

}
