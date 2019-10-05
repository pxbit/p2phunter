<?php
namespace Home\Controller;
use Common\Util\Pedis as Pedis;
use Common\Util\PPDApi as PPDApi;
use Think\Controller;
const MAIN_SERVER_NAME = "Main";
const BIDA_SERVER_NAME = "BidA";
const BIDB_SERVER_NAME = "BidB";
const PACE_INTERVAL_MS =  110;
class BidController extends BaseController {

    /* 进程名称 */
    private $getLoanListProcessName = "GetLoanList";
    private $getDetailProcessName = "GetDetail";
    private $loanHubProcessName = "LoanHub";
    private $storeDbProcessName = "StoreDb";
    private $safeLoanBidProcessName = "SafeLoanBid";
    private $paceControlProcessName = "PaceControl";
    //private $bidSystemStrategyProcessName = "BidSystem";
    //private $bidQryProcessName = "BidQry";
    //private $userUpdateProcessName = "UserUpdate";
    
    /* 缓存队列 */
    private $queueLoanList = "QLoanList";
    private $queueLoanDetail = "QLoanDetail";
    private $queueLoanDiy = "QLoanDiy";
    private $queueLoanSys = "QLoanSys";
    private $queueSafeLoan = "QSafeLoan";
    private $queueMatch = "QMatch";
    private $queueBidQry = "QBidQry";
    private $queueBidRecord = "QBidRecord";
    private $queueStoreDb = "QStoreDb";
    private $keyPaceControl = "KPaceControl";
    
    /*  SEVER可以根据名字选择， Main 主服， BidA 投标服务器A， BidB 投标服务器B。 后续可以添加更多不同的SERVER，
     * 此变量在startBid{$serverName} 任务入口函数中赋值，在后续任务中根据此SERVER不同，会有不同的行为和任务。
     */
    private $curServer = "Main"; 
    
    //与拍拍贷同步时间。
    private $time_diff = 0;
    private $sync_server="www.ppdai.com";
    
    private $qryAppIndex = 8;
    private $qryAppPrivateKey = null;
    private $qryAppId = null;
    
    public function _initialize(){
    	$m = M("appid");
    	$data = $m->where("AppIndex = '{$this->qryAppIndex}'")->find();
    	if($data){
    		$this->qryAppPrivateKey = $data['PrivateKey'];
    		$this->qryAppId = $data['AppId'];
    	}else{
    		$this->ppdLog("FatalError .. App Key Not Found\n");
    	}
    }
    
    
    public function stop()
    {
        $this->stopServer(MAIN_SERVER_NAME);
        $this->stopServer(BIDA_SERVER_NAME);
        $this->stopServer(BIDB_SERVER_NAME);
    }

    public function stopServer($server)
    {
        $this->curServer = $server;
        $redis = Pedis::getBidRedis(1);
        if($redis){
            $redis->del($this->curServer . $this->getLoanListProcessName . "StatusControl");
            $redis->del($this->curServer . "_1" . $this->getLoanListProcessName . "StatusControl");
            $redis->del($this->curServer . $this->getDetailProcessName . "StatusControl");
            $redis->del($this->curServer . $this->loanHubProcessName . "StatusControl");
            $redis->del($this->curServer . $this->safeLoanBidProcessName . "StatusControl");
            $redis->del($this->curServer . $this->storeDbProcessName . "StatusControl");
            $redis->del($this->curServer . $this->paceControlProcessName . "StatusControl");
            $redis->del($this->keyPaceControl);
            $redis->del("QryBidStatus");
            $redis->del($this->curServer . "SysBidStatus");
            $redis->del($this->curServer . "DiyBidStatus");
        }
    }
    
    public function startBidMain()
    {
        $curSever = MAIN_SERVER_NAME;
        //$this->curlStart("Home/Bid/startGetLoanList?curServer={$curSever}");
        //$this->curlStart("Home/Bid/startGetDetail?curServer={$curSever}");
        //$this->curlStart("Home/Bid/startLoanHub?curServer={$curSever}");
//         $this->curlStart("Home/Bid/startSafeLoanBid?curServer={$curSever}");
        //$this->curlStart("Home/Bid/startStoreDb?curServer={$curSever}");
        /*启动投标和其他整理任务*/
        //$this->curlStart("Home/Autobid/startBidding?servername={$curSever}");// 0 用户尾号为偶数的，1投用户位数为奇数的。2投所有
        //$this->curlStart("Home/Autobid/startDiyBidding?servername={$curSever}");
        //$this->curlStart('Home/Autobid/startQryBid');
        $this->curlStart('Home/Autobid/startNeaten');
        $this->curlStart("Home/Autobid/startSafeLoanBidding?servername={$curSever}");
    }
    
    public function startBidA()
    {
        $curSever = BIDA_SERVER_NAME;
        $this->curlStart("Home/Bid/startGetLoanList?curServer={$curSever}");
        //$this->curlStart("Home/Bid/startGetLoanList?curServer={$curSever}" . "_1");
        $this->curlStart("Home/Bid/startGetDetail?curServer={$curSever}");

        /* 启动投标进程 */
        $this->curlStart('Home/Autobid/startBidding?servername=BidA');// 0 用户尾号为偶数的，1投用户位数为奇数的。2投所有
        $this->curlStart('Home/Autobid/startDiyBidding?servername=BidA');
    }
    
    public function startBidB()
    {
        $curSever = BIDB_SERVER_NAME;
        $this->curlStart("Home/Bid/startGetLoanList?curServer={$curSever}");
        //$this->curlStart("Home/Bid/startGetLoanList?curServer={$curSever}" . "_1");
        $this->curlStart("Home/Bid/startGetDetail?curServer={$curSever}");

        /* 启动投标进程 */
        $this->curlStart('Home/Autobid/startBidding?servername=BidB');// 0 用户尾号为偶数的，1投用户位数为奇数的。2投所有
        $this->curlStart('Home/Autobid/startDiyBidding?servername=BidB');
    }

    /*  进程 任务 */
    public function startPaceControl($curServer)
    {
        $this->curServer = $curServer;
        $this->startProcess($this->paceControlProcessName);
    }
    
    public function startGetLoanList($curServer)
    {
        $this->curServer = $curServer;
        $this->startProcess($this->getLoanListProcessName);
    }
    
    public function startGetDetail($curServer)
    {
        $this->curServer = $curServer;
        $this->startProcess($this->getDetailProcessName);
    }
    
    public function startLoanHub($curServer)
    {
        $this->curServer = $curServer;
        $this->startProcess($this->loanHubProcessName);
    }
    
    public function startSafeLoanBid($curServer)
    {
        $this->curServer = $curServer;
        $this->startProcess($this->safeLoanBidProcessName);
    }
    
    public function startStoreDb($curServer)
    {
        $this->curServer = $curServer;
        $this->startProcess($this->storeDbProcessName);
    }
    
    /* 下面是进程启动器， 可以通过设置进程名称+StatusControl 控制进程， 每个名称只有一个进程，设置成 alive。
     * 进程名称+StatusControl 键值存在时，不会再启动同名进程。
    * 可以通过设置 进程名称+StatusControl = stop 停止相应进程，或者删除该键重启进程。
    * */
    
    public function startProcess($processName)
    {
        //设置进程运行时间为一直运行， 常驻内存。
        set_time_limit(3600*4.5);
        ignore_user_abort();
        $initTime=time();
        $processControllKey= $this->curServer . $processName . "StatusControl";
        $func = "process" . $processName;
        $redis = Pedis::getBidRedis(1);
        if($redis){
            //如果有其他刷投标记录进程活着，则退出。
            if($redis->exists($processControllKey)){
                $this->ppdLog($processName . " not started due to other alive ttl: " . $redis->ttl($processControllKey) . "\n");
                return;// 
            }
            $this->ppdLog("Redis connect OK, start $processName\n");
            $redis->set($processControllKey,"alive");
            $redis->expire($processControllKey, 60);
            $this->time_diff = $this->getTimeDiff($this->sync_server);
            
            //如果时间与拍拍贷时间差距过大，则提示。
            if($this->time_diff > 1000)
                $this->ppdLog("THIS SEVER TIME NEED AJUST, SERVER TIME DIFF " . $this->time_diff);
    
            //如果键$processControllKey 存在且值为 alive 则进程继续，并调用进程处理函数，否则进程结束。。
            while($redis->get($processControllKey) == "alive" && $redis->ttl($processControllKey) > 0){//forever
                $redis->expire($processControllKey, 60);
                /* 如果 进程运行超过4小时，则退出。 自动重启！ 以释放内存*/
                if (time() - $initTime > 14400)
                    $this->del($processControllKey);

                /* 开启进程轮训调度，每3s 退出，检查以下进程控制信号 */
                $startTime = time();
                while(time() - $startTime < 3)
                    $this->$func($redis);
            }
            $this->ppdLog("$processName manually  stoped\n");
        }else{
            $this->ppdLog("Redis connect failed! $processName failed to start\n",3);
        }
         
    }
    
    /* 控制getLoanList 节奏，让各服务器请求时间均匀分开。*/
    private function processPaceControl($redis)
    {
        sleep(10);
    }
    
    /* 进程轮询调度函数，必须60s内返回，否则会导致多进程重入并发
     * 为了方便后续处理详情， 信标每次最多写入5个， 赔标无限制 */
    public function testPace()
    {
        $redis = Pedis::getBidRedis(1);
        $timeout = $redis->pttl($this->keyPaceControl);
        echo  "init: no key set:" . $timeout;
        $redis->set($this->keyPaceControl, "server");
        $redis->pexpire($this->keyPaceControl, 100);
        $timeout = $redis->pttl($this->keyPaceControl);
        echo  "set: expire timeout shou be 100, actually:" . $timeout;
        usleep(100000);
        $timeout = $redis->pttl($this->keyPaceControl);
        echo  "set: expire timeout shou be -2, actually:" . $timeout;
        $redis->pexpire($this->keyPaceControl, 100);
        $timeout = $redis->pttl($this->keyPaceControl);
        echo  "set: will it suceed? , actually:" . $timeout;
    }
    
    private function processGetLoanList($redis)
    {
        static $startTime = 0;
        static $count = 0;
        static $totaltime = 0;
        if ($startTime == 0)
            $startTime = time();
        $pttl = $redis->pttl($this->keyPaceControl);
        if ($pttl < 0)
        {
            $redis->set($this->keyPaceControl, $this->curServer);
            $redis->pexpire($this->keyPaceControl, PACE_INTERVAL_MS);
            $start = $this->msectime();
            $loans = $this->getLoanList();
            $totaltime = $totaltime + ($this->msectime() - $start);
            $count = $count + 1;
            $safeLoan = array();
            $creditLoan = array();
            if (count($loans) > 0){
                foreach($loans as $loan){
                    if ($this->isNewListingId($loan['ListingId'])){
                        if($loan['CreditCode']=='AA' || $loan['CreditCode']=='AAA')
                            array_push($safeLoan, $loan);
                        else 
                            array_push($creditLoan, $loan);
                        /*控制信标每次最多不超过5个*/
                        if (count($creditLoan) > 5){
                            $redis->lpush($this->queueLoanList, json_encode($creditLoan));
                            $creditLoan = array();
                        }
                    }
                }
                if (count($safeLoan) > 0)
                    $redis->lpush($this->queueSafeLoan, json_encode($safeLoan));
                if (count($creditLoan) > 0)
                    $redis->lpush($this->queueLoanList, json_encode($creditLoan));
            }
            $timespan = $this->msectime() - $start;
            if ($timespan < PACE_INTERVAL_MS)
                usleep((PACE_INTERVAL_MS - $timespan)*1000);
        }else{
            usleep($pttl*1000);
        }

        if(time() - $startTime >= 60){
            $speed = $count/(time() - $startTime) * 60;
            $avgtime = $totaltime/($count + 0.0000001);
            $this->ppdLog("getloanlist speed $speed rpm, avg timespan $avgtime ms");
            $count  = 0;
            $totaltime = 0;
            $startTime = time();
        }
    }

    /* 获取标的列表 并获取详情 60内完成， 为了防止重复获取详情，详情可以存储在Redis中。
     * 经测试内网redis 写延迟0.35ms 检查键值存在延迟：0.15ms, 外网延迟约是内网6-7倍。 
     * */
    private function processGetDetail($redis)
    {
        /* 记速变量*/
        static $startTime = 0;
        static $count = 0;
        static $totaltime = 0;
        if ($startTime == 0)
            $startTime = time();

        /* 从redis 中读取带查询标列表*/
        $loan_id_list = array();
        $loanData = $redis->rpop($this->queueLoanList);
        if ($loanData){
            //$this->ppdLog("get data " . json_encode($loanData));
            $loans = json_decode($loanData, true);
            if($loans){
                foreach($loans as $loan){
                    if ($this->isNewListingId($loan['ListingId'])){
                        if(!$redis->exists($loan['ListingId'])){
                            array_push($loan_id_list, $loan['ListingId']);
                        }
                    }
                }
            }
        }

        /* 查询标详情*/
        if (count($loan_id_list) > 0){
            $begin = $this->msectime();
            $loanDetail = $this->getLoanDetail($loan_id_list);
            $totaltime = $totaltime + ($this->msectime() - $begin);
            $count = $count + 1;
            $newDetail = array();
            foreach ($loanDetail as $detail){
                if(!$redis->exists($detail['ListingId'])){
                    $redis->set($detail['ListingId'], 1);
                    $redis->expire($detail['ListingId'],3600);
                    array_push($newDetail, $detail);
                }
            }
            if (count($newDetail) > 0)
                $redis->lpush($this->queueLoanDetail, json_encode($newDetail));
        }
        usleep(1000);
        if ((time() % (3600*24)) == 0)
            $this->time_diff = $this->getTimeDiff($this->sync_server);

        /* 计算API 调用速度*/
        if(time() - $startTime >= 60){
            $speed = $count/(time() - $startTime);
            $avgtime = $count == 0? $count: $totaltime/$count;
            $this->ppdLog("getloandetail speed $speed, avg timespan:$avgtime");
            $count  = 0;
            $totaltime = 0;
            $startTime = time();
        }
    }
    
    /* 仅在主服运行 */
    private function processLoanHub($redis){
        if ($this->curServer == MAIN_SERVER_NAME){
            $loadDetail = $redis->rpop($this->queueLoanDetail);
            $uniqueLoanDetail = array();
            while($loadDetail){
                $loadDetailArray = json_decode($loadDetail, true);
                foreach ($loadDetailArray as $detail){
                    if ($this->isNewListingId($detail['ListingId'])){
                        array_push($uniqueLoanDetail, $detail);
                    }
                }
                $loadDetail = $redis->rpop($this->queueLoanDetail);
            }
            if (count($uniqueLoanDetail) > 0){
                $redis->lpush($this->queueLoanDiy, json_encode($uniqueLoanDetail));
                $redis->lpush($this->queueLoanSys, json_encode($uniqueLoanDetail));
                $redis->lpush($this->queueStoreDb, json_encode($uniqueLoanDetail));
            }
            usleep(1000);
        }else{
            sleep(1);
        }
    }
    
    /* 将用户余额定期更新，并将有效用户信息定期整理 存到redis 供投标使用
     * 因为用户较多，此函数可能无法再60s内完成，
     * 所以特殊处理，需要在进程中不断更新 Redis 状态*/
    private function processUserUpdate($redis)
    {
        $queueRefreshToken = "RefreshToken";
        $processControllKey= $this->userUpdateProcessName . "StatusControl";
        $redis->expire($processControllKey, 600);//设置10分钟过期, 进程需要在10分钟内完成。
        $um = M("user");
        $tomorrow = date("Y-m-d H:i:s", strtotime("+1 day"));
        $data = $um->where("Score > 5000 and RTExpireDate > '{$tomorrow}'")->field("UserId, Score, AccessToken, RefreshToken, ATExpireDate, RTExpireDate")->select();
        if($data){
            foreach ($data as $user){
                //TODO 过期的TOKEN 刷新，存数据库，
                if($data['ATExpireDate'] < $tomorrow){
                    $redis->lpush($queueRefreshToken, json_encode($user));
                }else{
                   //TODO MAKE GET BALANCE REQUEST
                }
                //刷新用户余额。
            }
        }else{
            $this->ppdLog("processUserUpdate No Valid User Found in DB",3);
        }
        
    }
    
    /* 赔标投标 */
    private function processSafeLoanBid($redis)
    {
        $safeLoan = $redis->rpop($this->queueSafeLoan);
        sleep(60);
        //TODO 赔标投标 已在AUTOBID 里面实现
    }
    
    /* 将信用标详情存储到数据库 */
    private function processStoreDb($redis)
    {
        if ($this->curServer == MAIN_SERVER_NAME){
            $loadDetail = $redis->rpop($this->queueStoreDb);
            while($loadDetail){
                $data = json_decode($loadDetail, true);
                if($data){
                    $loan=M("history_loan");
                    if($loan->addAll($data,array(),true) === false)
                        $this->ppdLog("processStoreDb store failed! due to DB error");
                }
                $loadDetail = $redis->rpop($this->queueStoreDb);
            }
        }
        sleep(2);
        
    }
    
    /* 缓存最近的100个ID列表，防止同样的ID同一SERVER多次处理。
     * 经测试100个ID处理时间不到1毫秒，时间可以忽略。
     * 每个服务器每个进程都独自维护一个静态列表，因此次函数无法避免多服务或者多进程键ID重复。
    */
    private function isNewListingId($list_id)
    {
        static $latest_list_id_queue = array();
        if (in_array($list_id, $latest_list_id_queue))
            return false;
        else {
            array_push($latest_list_id_queue, $list_id);
            if (count($latest_list_id_queue) > 100)
                array_shift($latest_list_id_queue);
            return true;
        }
    }

    
    public function getLoanList(){
        //获取所有借款列表，API 每页200条，获取到 每页不是200条表明是最后一页，将所有列表合成为一个，返回列表，如果失败，返回空表。
        $page_index = 1;
        $loan_list_array=array();
        #$this->time_diff = $this->getTimeDiff($this->sync_server);
        $last_time = $this->mSyncTime($this->time_diff) - 5000;
        do {
            $list=array();
            $url = "https://openapi.ppdai.com/listing/openapiNoAuth/loanList";
            $startDateTime = date("Y-m-d H:i:s", $last_time/1000) . "." . substr($last_time, -3);
            $request = '{ "PageIndex": ' . $page_index . ',    "StartDateTime": "'. $startDateTime . '" }';
            $result = $this->multiAccountSend($url, $request);
            if((isset( $result['Result'])&& $result['Result']==1))
            {
                $list= $result['LoanInfos'];
            }else {
                if(json_encode($result)==false||json_encode($result)==null)
                    $this->ppdLog("getLoanList result ERR:" . $result,2);
                else
                    $this->ppdLog("getLoanList json result ERR:" . json_encode($result), 2);
            }
            $loan_list_array=array_merge($loan_list_array,$list);
            $page_index = $page_index+1;
        }while (count($list)>=200);
        return  $loan_list_array;
    }
    
    private function getLoanDetail($loan_id_list){
        $url = "https://openapi.ppdai.com/listing/openapiNoAuth/batchListingInfo";
        $request= '{ "ListingIds": [';
        foreach ($loan_id_list as $id){
            $request=$request . $id . ',';
        }
        $request = rtrim($request, ",");
        $request= $request  . '] }';
        $result = $this->multiAccountSend($url, $request);
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
    
    private function makeRequest($url, $request, $accesstoken = '')
    {
        $timestamp = gmdate ( "Y-m-d H:i:s", $this->syncTime($this->timediff)); // UTC format
        $timestap_sign = $this->sign($this->appid. $timestamp);
        $requestSignStr = $this->sortToSign($request);
        $request_sign = $this->sign($requestSignStr);
    
        $header = array ();
        $header [] = 'Content-Type:application/json;charset=UTF-8';
        $header [] = 'X-PPD-TIMESTAMP:' . $timestamp;
        $header [] = 'X-PPD-TIMESTAMP-SIGN:' . $timestap_sign;
        $header [] = 'X-PPD-APPID:' . $this->appid;
        $header [] = 'X-PPD-SIGN:' . $request_sign;
        $header [] = 'X-PPD-ACCESSTOKEN:' . $accesstoken;
    
        $option = array(
                CURLOPT_RETURNTRANSFER => '1',
        );
        $req = new \Home\Controller\CurlRequestController ($url, 'POST',$request, $header, $option);
        return $req;
    }
    
    
    public    function SendRequest ( $url, $request, $appId, $privateKey, $accessToken){
            $curl = curl_init ( $url );
            $timestamp = gmdate ( "Y-m-d H:i:s", time ()); // UTC format
            $timestap_sign = $this->sign($appId. $timestamp,$privateKey);
        
            $requestSignStr = $this->sortToSign($request);
            $request_sign = $this->sign($requestSignStr,$privateKey);
        
            $header = array ();
            $header [] = 'Content-Type:application/json;charset=UTF-8';
            $header [] = 'X-PPD-TIMESTAMP:' . $timestamp;
            $header [] = 'X-PPD-TIMESTAMP-SIGN:' . $timestap_sign;
            $header [] = 'X-PPD-APPID:' . $appId;
            $header [] = 'X-PPD-SIGN:' . $request_sign;
            if ($accessToken!= null)
                $header [] = 'X-PPD-ACCESSTOKEN:' . $accessToken;
            curl_setopt ( $curl, CURLOPT_HTTPHEADER, $header );
            curl_setopt ( $curl, CURLOPT_POST, 1 );
            curl_setopt ( $curl, CURLOPT_POSTFIELDS, $request );
            curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, 1 );
            $result = curl_exec ( $curl );
            curl_close ( $curl );
            $j = json_decode ( $result, true );
            if ($j == NULL || $j == false) {
                return $result;
            }
            return $j;
        }
        
        public function multiAccountSend($url, $data){
            if ($this->curServer == MAIN_SERVER_NAME){
                return $this->multiAccountSendMain($url, $data);
            }else if ($this->curServer == BIDA_SERVER_NAME){
                return $this->multiAccountSendGroupA($url, $data);
            }else if ($this->curServer == BIDB_SERVER_NAME){
                return $this->multiAccountSendGroupB($url, $data);
            }else{
                return $this->multiAccountSendMain($url, $data);
            }
        }
        
        public function multiAccountSendMain($url, $data) {
            return $this->SendRequest ( $url, $data, $this->qryAppId, $this->qryAppPrivateKey, null);
        }
        
        public function multiAccountSendGroupA($url, $data) {
            static $i = 0;
            if($i == 0){
                $cur_appid = $this->appid_stockplayer;
                $cur_key = $this->appPrivateKey_stockplayer;
            }else{
                $cur_appid = $this->appid_laosiji;
                $cur_key = $this->appPrivateKey_laosiji;
            }
            $i=($i+1)%2;
            return $this->SendRequest ( $url, $data, $cur_appid, $cur_key, null);
        }
        
        public function multiAccountSendGroupB($url, $data) {
            static $i = 0;
            if($i == 0){
                $cur_appid = $this->appid_n5;
                $cur_key = $this->appPrivateKey_n5;
            }else{
                $cur_appid = $this->appid_ppzhushou;
                $cur_key = $this->appPrivateKey_ppzhushou;
            }
            $i=($i+1)%2;
            return $this->SendRequest ( $url, $data, $cur_appid, $cur_key, null);
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
        
}
