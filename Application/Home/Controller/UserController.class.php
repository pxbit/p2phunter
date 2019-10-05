<?php
namespace Home\Controller;
use Think\Cache;

use Think\Controller;
use Common\Util\LCache as LC;

const INVALID_DELAY_RATIO = 0xffff;
const SYS_CACHE_TIMEOUT   = 86400; /* 每24个小时更新一次 */
const BID_DATA_CACHE_TIME = 86400;
const LPR_QUERY_TIMEOUT   = 86400;
const OVERDUE_QUERY_DAYS  = 180;
const START_OF_SYS_DAY    = '2017-06-01 00:00:00';

/* ppd assessment error code */
const ERRCODE_ASSESS_UNFINISH  = 5001;
const ERRCODE_ASSESS_EXPIRE    = 5002;
const ERRCODE_ASSESS_IDENTICAL = 5003;


class UserController extends BaseController {
    public function _initialize(){
        /* init global config */
        $this->disConfig = C("DISCOUNT_CFG");
        $this->prtConfig = C("PROMOTE_CFG");

        /* init global table */
        $this->lprTbl = M("lpr");
        $this->bidTbl = M("bid");

        /* init URL */
        $urlPrefix = __ROOT__ . "/home/user";
        $this->assign('userOwUrl'     , $urlPrefix . "/listOverview");    //type:1
        $this->assign('bidStatisUrl'  , $urlPrefix . "/statisticsBid");   //type:2
        $this->assign('interestAnaUrl', $urlPrefix . "/analyseInterest"); //type:3
        $this->assign('rechargetUrl'  , $urlPrefix . "/rechargeAccount"); //type:4
        $this->assign('todayBidUrl'   , $urlPrefix . "/todayBidStat");    //type:5
    }

    public function index($mobile=0, $type=0){
        if($this->isUserLogin() && !$this->isCookieValid()){
            cookie('uid',session('uid'),3600*24);
            cookie('time',session('time'),3600*24);
            cookie('sid',session('sid'),3600*24);
        }
        if($this->isCookieValid()){
            $uid=cookie('uid');
            $m=M("User");
            $u = $m->where("UserId='{$uid}'")->find();
            if($u){
                $this->assign("username",$m->UserName);
                $this->assign("Score",$m->Score);
                $this->assign("Money",floor($m->Score/100)/100.0);
                if($u['JdCode']!=null)
                    $jdcode = $u['JdCode'];
                else
                    $jdcode = cookie("jdcode");
                $this->assign("jdcode",$jdcode);
            }
            else {
                $this->ppdLog("Cannot found user {$uid}",2);
                $this->assign("username","");
            }

            $this->setPageInfo();
            $this->assign("type", 1); //登陆用户页面缺省显示用户总览

            $this->setAccessType();

            $this->displayPage($mobile, "default/user", "default/user_m");
        }
        else{
            $this->displayPage($mobile, "default/login", "default/login_m");
        }
    }

    public function setPageInfo() {
        $payOption = array();
        foreach($this->disConfig as $price => $dis) {
            $a = array('price' => $price, 'discount' => $dis);
            array_push($payOption, $a);
        }
        $this->assign("payOption", $payOption);
        $this->assign('loginMsg', $this->getLoginMsg());
    }

    public function getPayInfoStr(){
      $startDate =  date('Y-m-d',strtotime($this->prtConfig['START_DATE']));
      $endDate   =  date('Y-m-d',strtotime($this->prtConfig['END_DATE']));
      $paytip = '迎双十一，充值大优惠'.
        '，满100送' .
        $this->disConfig['100'] .
        '，满200送'.$this->disConfig['200'] .
        '，满500送'.$this->disConfig['500'] .
        '，满1000送'.$this->disConfig['1000'] .
        '，本地API注册会员优惠翻倍!&nbsp&nbsp活动时间段:&nbsp' .
        '[' . $startDate . ']&nbsp-&nbsp [' . $endDate . ']' . 
        '本活动期间，推荐优惠活动暂停。';
        $this->ajaxReturn($paytip);
    }

    public function testauth()
    {
        echo "test auth";
        $this->auth("dev", "abd");
        echo "done";
    }

    public function multiAuth($mobile=0)
    {
        $user_id = $this->isCookieValid();
        if(!$user_id)
            $user_id=$this->isUserLogin();
        if(!$user_id){
            $this->assign('errorMsg', "登录超时");
            $this->display("default/error");
            return;
        }

        $appids  = M("appid");
        $allid = $appids->field("AppIndex, AppId")->where(1)->select();


        if ($allid){
            $multiAuth = M("user_multi_auth");
            $userAuth = $multiAuth->where("UserId = '{$user_id}'")->select();
            if($userAuth){
                foreach ($userAuth as $auth){

                    foreach ($allid as $id=>$appid){
                        if($appid['AppIndex'] == $auth['AppIndex']){
                            $allid[$id]['RTExpireDate'] = $auth['RTExpireDate'];
                            $allid[$id]['Status'] = $auth['Status'];
                        }
                    }
                }
            }
            $this->assign("authStatus", json_encode($allid, true));
        }else{
            $this->assign("authStatus", "");
        }

        if ($mobile == 1){
          $this->display("default/auth_m");
        } else {
          $this->display("default/auth");
        }
    }
    /* $i 拍拍贷APPID， $code 拍拍贷回调给的code。
     * 根据$i 查出appid index ，根据 code 解析出用户的授权信息
     * 保存在user_multi_auth表中。
     * */
    public function auth($i, $code)
    {
        $this->setPageInfo();
        flush();
        /* 判断用户登录状态，获取用于ID。 本授权只有登录后才可以开启。*/
        $user_id = $this->isCookieValid();
        if(!$user_id)
            $user_id=$this->isUserLogin();
        if(!$user_id){
            $this->assign('errorMsg', "登录超时");
            $this->display("default/error");
            return;
        }

        /*获取用户名，防止用户授权到其他账户。导致错乱*/
        $mu = M("user");
        $user = $mu->where("UserId = '{$user_id}'")->find();
        if($user){
            $cur_username = $user['UserName'];
        }else{
            $this->ppdLog("fatal error UserName Not found for user $user_id", 3);
            return;
        }

        /* 获取appIndex*/
        $appid = $i;
        $authDb = M("appid");
        $result = $authDb->where("AppId = '{$i}'")->find();
        if (!$result){
            $this->assign('errorMsg', "非法ID");
            $this->display("default/error");
            return;
        }
        $appIndex = $result['AppIndex'];
        $appPrivateKey = $result['PrivateKey'] . "\n";

        //获取授权
        $auth = $this->getAuthorize($code, $appid);
        if(isset($auth['OpenID'])){
            $multiAuth = M("user_multi_auth");
            $openId = $auth['OpenID'];

            //获取到用户名
            $userAuth = $multiAuth->where("UserId = '{$user_id}' and AppIndex = '{$appIndex}'")->find();
            if($userAuth){//用户授权过 （大部分情况）
                $userAuth['AccessToken'] = $auth['AccessToken'];
                $userAuth['RefreshToken'] = $auth['RefreshToken'];
                $userAuth['OpenID'] = $auth['OpenID'];
                $userAuth['ATExpireDate'] = date("Y-m-d H:i:s", time() + $auth['ExpiresIn']);
                $userAuth['RTExpireDate'] = date("Y-m-d H:i:s", time() + 3600*24*90);
                $userAuth['Status'] = 0;
                $status = $multiAuth->where("UserId = '{$user_id}' and AppIndex = '{$appIndex}'")->save($userAuth);
                if($status === false)
                    $this->ppdLog("Update userAuth DB ERROR " . json_encode($userAuth));
            }else if ($userAuth === null){//用户没授权过这个APPID。
                $username=$this->getPPDName($auth['OpenID'], $auth['AccessToken'], $appid, $appPrivateKey);
                if($cur_username != $username){
                    $this->ppdLog("username must be the same:" . "$username and $cur_username");
                    $this->assign("errorMsg", "不能授权不同账户");
                    $this->display("default/error");
                    return;
                }
                if($username){//获取到用户名
                    $data['UserId'] = $user_id;
                    $data['UserName'] = $username;
                    $data['OpenID'] = $auth['OpenID'];
                    $data['AppIndex'] = $appIndex;
                    $data['AccessToken'] = $auth['AccessToken'];
                    $data['RefreshToken'] = $auth['RefreshToken'];
                    $data['ATExpireDate'] = date("Y-m-d H:i:s", time() + $auth['ExpiresIn']);
                    $data['RTExpireDate'] = date("Y-m-d H:i:s", time() + 3600*24*90);
                    $data['CreateTime'] = date("Y-m-d H:i:s", time());
                    $data['Status'] = 0;
                    $status = $multiAuth->add($data);
                    if($status === false)
                        $this->ppdLog("ADD TO userAuth DB ERROR " . json_encode($data));
                }else{
                    $this->ppdLog("user name not found" . json_encode($auth));
                }
            }else{
                $this->ppdLog("qry user_multi_auth db error" . json_encode($auth));
            }

        }else {
            $this->ppdLog("Authorization failed... ",2);
        }
        $this->redirect('user/multiAuth');
    }

    public function ppd($code)
    {
        $this->setPageInfo();
        $this->display("default/user");
        flush();
        $auth=$this->getAuthorize($code);
        if(isset($auth['OpenID'])){
            $username=$this->getUserNameFromDb($auth['OpenID']);
            if(!$username)
                $username=$this->getPPDName($auth['OpenID'], $auth['AccessToken']);
            if($username){
                $auth['ATExpireDate']=date("Y-m-d h:i:s",time()+$auth['ExpiresIn']);
                $auth['RTExpireDate']=date("Y-m-d h:i:s",time()+3600*24*90);
                $auth['UserName']=$username;
                unset($auth['ExpiresIn']);
                $user=M('User');
                $result=$user->where("UserName='{$username}'")->find();
                if($result){
                    $id=$user->where("UserName='{$username}'")->save($auth);
                    if($id===false)
                    {
                        $this->ppd("USER TOKEN DB UPDATE ERROR:USERNAME:".$username,2);
                        $this->redirect('Index/index');
                    }
                }else{
                    $auth['CreateTime']=date("Y-m-d h:i:s",time());
                    $id=$user->add($auth);  
                    if(!$id){
                        $this->ppd("USER TOKEN DB ADD ERROR:USERNAME:".$username,2);
                        $this->redirect('Index/index');
                    }
                }
                $data=$user->where("UserName='{$username}'")->find();
                if($data){
                    $time=time();
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $sid=md5($auth['OpenID'] . $data['UserId']. $time . $ip . $auth['OpenID']);
                    session('oid',$auth['OpenID'],3600);
                    session('uid',$data['UserId'],3600);
                    session('time',$time,3600);
                    session('sid',$sid,3600);

                    $this->setPageInfo();
                    redirect('index');
                    return;
                }else {
                    $this->ppd("WIRED ..CANNOT FIND USERNAME:".$username,3);
                    $this->redirect('Index/index');
                }
            }else{
                echo "拍拍贷授权获取用户名失败，请稍后重试...</br>";
                flush();
                $this->ppdLog("UserName get failed... ID:" . $auth['OpenID'],2);
                usleep(2000*1000);
                $this->redirect('Index/index');
            }
        }else {
          echo "拍拍贷授权失败，请稍后重试...</br>";
          flush();
          $this->ppdLog("Authorization failed... ",2);
          usleep(2000*1000);
          $this->redirect('Index/index');
        }
    }


    function getAppIdFromIndex($appIndex){
        $appid = M("appid");
        $data = $appid->where("AppIndex = '{$appIndex}'")->cache(60)->find();
        if($data === false){
            $this->ppdLog("获取APPID 数据库错误！");
        }else if ($data == null){
            $this->ppdLog("APPID： $appIndex 未找到");
        }else{
            return $data;
        }
        return false;
    }

    /*
     * 根据OpenID查询用户信息,其中username需要用私钥解
    * 似乎存在问题，目前无法使用。
    * */
    function getPPDName($OpenID, $accesstoken, $appid = null, $privateKey = null)
    {
        //首先获取APPID信息和私钥信息，多授权需要分别用对应的APPID去获取用户名。
        $UserName=NULL;
        $url = "https://openapi.ppdai.com/open/openApiPublicQueryService/QueryUserNameByOpenID";
        $request = '{"OpenID": "' . $OpenID . '" }';
        $j = $this->send($url, $request, $accesstoken, $appid, $privateKey);
        if(isset($j['ReturnCode'])){
            if($j['ReturnCode']==0){
                $UserName=$this->decrypt($j['UserName'], $privateKey);
            }else{
                //log errmsg
                $this->ppdLog("UserName get failed! OpenID:" . $OpenID . "ERROR:" , $j['ReturnMessage'],2);
            }
        }else{
            $this->ppdLog("UserName get failed! unknown error: j:$j",2);
        }
        return $UserName;
    }
    function getUserNameFromDb($OpenID)
    {
        $user=M('User');
        $data=$user->where("OpenID='{$OpenID}'")->find();
        if($data && strlen($data['UserName'])>2)
            return $data['UserName'];
        else return null;
    }

    public function getUserOpenID($userId){
        $user=M('User');
        $data=$user->where("UserId='{$userId}'")->find();
        if($data && strlen($data['UserName'])>2){
            return $data['OpenID'];
        } else {
           return null;
        }

    }

    public function getUserId($openId){
        $user=M('User');
        $cacheName = 'UserIdTbl';
        if (LC::isCacheValid($cacheName)){
            $tbl = LC::getCache($cacheName);
            return array_search($openId, $tbl);
        }

        $userSet = $user->where(1)->field('UserId, OpenID')->cache(true)->select();
        foreach($userSet as $u){
            $tbl[$u['UserId']] = $u['OpenID'];
        }
        LC::cache($cacheName, $tbl, SYS_CACHE_TIMEOUT);
        return array_search($openId, $tbl);
    }

    public function getUserAssessmentMsg(){
        $userid=$this->isCookieValid();
        if(!$userid){
            $userid=$this->isUserLogin();
        }
        if(!$userid){
            return null;
        }

        $cdt['UserId'] = $userid;
        $user = M("user");
        $data = $user->where($cdt)->find();
        if(!$data){
            return "null";
        }

        $res = $this->getAssessment($data['AccessToken']);
        $evaCdt =(!strcmp('谨慎型', $res['EvaluationType'])) ||
                 (!strcmp('稳健性', $res['EvaluationType']));
        if(ERRCODE_ASSESS_UNFINISH == $res['ResultCode']){
            $msg = '您未完成风险评测，请稍后再试';
        }else if (ERRCODE_ASSESS_EXPIRE == $res['ResultCode']){
            $msg = '您的风险测评结果已过期，请重新测评';
        }else if (ERRCODE_ASSESS_IDENTICAL == $res['ResultCode']){
            $msg = '测评结果和标的等级不一致';
        }else if ($evaCdt){
            $m = M("strategy");
            $cnt = $m->where("UserId='{$userid}' AND (StrategyId<'1000' or StrategyId>'100000')")->count();
            if($cnt > 0){
                $msg = '请到拍拍贷官网测评';
            }
        }
        return $msg;
    }

    public function getLoginMsg(){
      $msg = '请务必在授权管理中开启多个授权， 否则将无法投标';
      /* $msg = $this->getUserAssessmentMsg(); */
      return $msg;
    }


    /**
     * 用户退出
     */
    public function logout(){
            session('oid',null);
            session('time',null);
            session('uid',null);
            session('sid',null);
            cookie('oid',null);
            cookie('time',null);
            cookie('uid',null);
            cookie('sid',null);
            $this->redirect('Index/index');
    }

    public function getEnabledSysStgCnt($UserId)
    {
        $UserId=(int)$UserId;
        $m=M("strategy_setting");
        $condition['UserId']=$UserId;
        return $m->where("UserId='{$UserId}' AND BidAmount >=50")->count();
    }

    public function getEnabledDiyStgCnt($UserId)
    {
        $UserId=(int)$UserId;
        $m=M("personal_strategy");
        return $m->where("UserId='{$UserId}' AND BidAmount >=50")->count();
    }

    public function getUserInfoOrig()
    {
        $userid=$this->isCookieValid();
        if(!$userid)// user just login. cookie not send.
            $userid=$this->isUserLogin();
        if($userid){
            $condition['UserId']=$userid;
            $user=M("user");
            $data=$user->where($condition)->find();
            if($data){
                $info=array();
                $info['status']=0;
                $info['balance']=$this->getBalance($data['AccessToken']);
                $info['expire_date']=$data['RTExpireDate'];
                $info['enable_sys_strategy_count']=$this->getEnabledSysStgCnt($userid);
                $info['enable_diy_strategy_count']=$this->getEnabledDiyStgCnt($userid);
                $bid=M("bid");
                $lpr=M("lpr");
                $weekago=date("Y-m-d H:i:s",time()-3600*24*7);
                //$bid->where("UserId='{$userid}'")->select();
                $info['total_bid_amount']=$bid->where("UserId='{$userid}'")->sum('BidAmount')+0;
                $info['total_bid_count']=$bid->where("UserId='{$userid}'")->count();
                $info['total_bid_gain']=$lpr->where("UserId='{$userid}'")->sum('RepayInterest')+0;
                $info['total_repay_count']=$lpr->where("UserId='{$userid}' and RepayInterest>0")->count();
                $info['total_delay_amount']=$lpr->where("UserId='{$userid}' and OverdueDays>7 and RepayStatus=0 and UpdateTime >'{$weekago}'")->sum('OwingPrincipal')+0;
                                            //+$lpr->where("UserId='{$userid}' and DueDate<'{$yestoday}'")->sum('OwingInterest')+0;
                $total_delay_count=$lpr->where("UserId='{$userid}' and OverdueDays>7 and RepayStatus=0 and UpdateTime >'{$weekago}'")->count();
                $info['total_delay_ratio']=round($total_delay_count*100/($info['total_repay_count']+0.001),2);//TO BE DONE
                $infodata=json_encode($info);
                $this->ajaxReturn($infodata);
            }else{
                $this->ppdLog("invalidlogin detected</br>\n",3);
                die("-3");//invalidlogin;
            }
        }else{
            echo " not login";
        }
    }

    public function getUserInfo() {
        $userid=$this->isCookieValid();
        if(!$userid){
            $userid=$this->isUserLogin();
        }
        if(!$userid){
            echo " not login";
        } else {
            $cdt['UserId'] = $userid;
            $user = M("user");
            $data = $user->where($cdt)->find();
            if($data){
                $info=array();
                $info['status']  = 0;
                $info['balance'] = $this->getBalance($data['AccessToken']);
                $info['expire_date']= $data['RTExpireDate'];
                $info['enable_sys_strategy_count'] = $this->getEnabledSysStgCnt($userid);
                $info['enable_diy_strategy_count'] = $this->getEnabledDiyStgCnt($userid);

                $bidInfo    = $this->fetchUserBidInfo($userid);
                $bidInfoTdy = $this->fetchUserBidInfoTdy($userid);
                $lprInfo    = $this->fetchUserLprInfo($userid);
                $info       = $info + (array)$bidInfo + (array)$bidInfoTdy + (array)$lprInfo;
                $infodata   = json_encode($info);
                $this->ajaxReturn($infodata);
            }else{
                $this->ppdLog("invalidlogin detected</br>\n",3);
                die("-3");
            }
        }
    }

    public function fetchUserBidInfo($uid){
        $cacheName = "userBidInfo_{$uid}";
        if (true == LC::isCacheValid($cacheName)){
            $info = LC::getCache($cacheName);
        }else{
            $bid = M("bid");
            $info['total_bid_amount'] = $bid->where("UserId='{$uid}'")->sum('BidAmount')+0;
            $info['total_bid_count']  = $bid->where("UserId='{$uid}'")->count();
            LC::cache($cacheName, $info, SYS_CACHE_TIMEOUT);
        }
        return $info;
    }

    public function fetchUserLprInfo($uid){
        $cacheName = "userLprInfo_{$uid}";
        if (true == LC::isCacheValid($cacheName)){
            $info = LC::getCache($cacheName);
        }else{
            $lpr = M("lpr");
            $info['total_bid_gain']     = round($lpr->where("UserId='{$uid}'")->sum('RepayInterest')+0.0, 0);
            $info['total_repay_count']  = $lpr->where("UserId='{$uid}' and RepayInterest>0")->count();

            $weekago = date("Y-m-d H:i:s",time() - 3600 * 24 * 7);
            $cdt = "UserId='{$uid}' and OverdueDays>7 and RepayStatus=0 and UpdateTime >'{$weekago}'";
            $info['total_delay_amount'] = round($lpr->where($cdt)->sum('OwingPrincipal')+0, 0);

            $cdt ="UserId='{$uid}' and OverdueDays>7 and RepayStatus=0 and UpdateTime >'{$weekago}'";
            $total_delay_count = $lpr->where($cdt)->count();
            $info['total_delay_ratio'] = round($total_delay_count * 100 / ($info['total_repay_count'] + 0.001), 1);
            LC::cache($cacheName, $info, SYS_CACHE_TIMEOUT);
        }
        return $info;
    }

    public function fetchUserBidInfoTdy($uid){
        $bidRcnt = M("bid_recent");
        $tdy_brk = date("Y-m-d", time());
        $yes_brk = date("Y-m-d", time() - 3600 * 24 * 1);

        $cdt = "UserId='{$uid}' and BidTime >= '{$tdy_brk}'";
        $info['tdy_bid_amount'] = $bidRcnt->where($cdt)->sum('BidAmount')+0;
        $info['tdy_bid_count']  = $bidRcnt->where($cdt)->count();

        $cdt = "UserId='{$uid}' and BidTime>='{$yes_brk}' and BidTime<'{$tdy_brk}'";
        $info['yes_bid_amount'] = $bidRcnt->where($cdt)->sum('BidAmount')+0;
        $info['yes_bid_count']  = $bidRcnt->where($cdt)->count();
        return $info;
    }

    public function getStrategyNameList()//api
    {
        $m=M("strategy");
        $n=M("personal_strategy");
        $uid=cookie('uid');
        if(!$uid)//in case just login. cookie not send.
            $uid=session('uid');
        $data=$m->cache(true,120)->getField('StrategyId,Name');
        $data_diy=$n->where("UserId='{$uid}'")->cache(true,60)->getField('StrategyId,StrategyName');
        if($data){
            if($data_diy){
                foreach($data_diy as $id=>$diy){
                    $data[$id]=$diy;
                }
            }
            $data['status']=0;
        }else
            $data['status']=1;
        $infodata=json_encode($data);
        $this->ajaxReturn($infodata);
    }

    public function getEnabledStrategyNameList()
    {
        $uid = cookie('uid');
        if(!$uid){
            $uid = session('uid');
        }

        $sysMdl = M("strategy");
        $psnMdl = M("personal_strategy");

        $sysSet = $sysMdl->join('JOIN ppd_bid ON ppd_bid.StrategyId = ppd_strategy.StrategyId')
            ->where("UserId={$uid} and status >=0")->field('ppd_strategy.StrategyId, ppd_strategy.Name')->cache(true, SYS_CACHE_TIMEOUT)->select();
        $listSys = array();
        foreach($sysSet as $key => $val){
            $id   = $val['StrategyId'];
            $name = $val['Name'];
            if(empty($listSys[$id])){
                $listSys[$id] = $name;
            }
        }

        $listPsn = $psnMdl->where("UserId='{$uid}'")->cache(true, SYS_CACHE_TIMEOUT)->getField('StrategyId,StrategyName');
        /* array_merge如果入参为null,则返回null,所以这里需要将null转换为空数组 */
        $list    = (array)$listSys + (array)$listPsn;
        if(!empty($list)){
            $list['status'] = 0;
        } else {
            $list['status'] = 1;
        }
        $infodata = json_encode($list);
        $this->ajaxReturn($infodata);
    }

    public function getBidList($type=-1,$time=7,$page=1)//api
    {
        $m=M("bid");
        $uid=cookie('uid');
        if(!$uid)//in case just login. cookie not send.
            $uid=session('uid');
        $BidTime=date("Y-m-d H:i:s",strtotime("-{$time} day"));
        $map['BidTime']=array('egt',$BidTime);
        $map['UserId']=$uid;
        if($type>0)
            $map['ppd_bid.StrategyId']=$type;

        $data=$m->where($map)->page($page,20)->field("BidTime,BidAmount,BidCost,ListingId,StrategyId")->order("BidTime desc")->cache(true,60)->select();
        if(!empty($data)){
            $sys_strategy=M("strategy");
            $sys=$sys_strategy->where("StrategyId<'1000'")->cache(true,120)->select();
            $diy_strategy=M("personal_strategy");
            $diy=$diy_strategy->cache(true,60)->where("UserId='{$uid}'")->select();
            foreach ($data as $id=>$record){
                $data[$id]['Name']="神马策略";
                if(!empty($sys)){
                    foreach($sys as $item){
                        if($item['StrategyId']==$record['StrategyId'])
                            $data[$id]['Name']=$item['Name'];
                    }
                }
                if(!empty($diy)){
                    foreach($diy as $item2){
                        if($item2['StrategyId']==$record['StrategyId'])
                            $data[$id]['Name']=$item2['StrategyName'];
                    }
                }
            }
            $data['status']=0;
            $data['current_page']=$page;
            $count=$m->where($map)->cache(true,60)->count();
            $data['total_page']=ceil($count/20);
        }
        else{
            if($data===false)
                $this->ppdLog("dbError:{$m->getDbError()},last_sql:{$m->getLastSql()}",2);
            $data['status']=1;
            //$data['err']=$m->getDbError();
            //$data['last_sql']=$m->getLastSql();
        }
        $infodata=json_encode($data);
        echo $infodata;
    }

    public function listOverview(){
        $type=1;
        $this->assign("type", $type);
        $this->index();
    }

    public function statisticsBid($mobile=0){
        $type=2;
        $this->assign("type", $type);
        if ($mobile == 0){
          $this->display("default/user");
        } else {
          $this->assign("mbl", 1);
          $this->display("default/user_m");
        }
    }

    public function analyseInterest($mobile=0){
        $userid=$this->isCookieValid();
        if(!$userid){
            $userid=$this->isUserLogin();
            $this->ppdLog("user/analyseInterest: user didnot login");
            return null;
        }

        $cacheName = "briefInterest_{$userid}";
        if (true == LC::isCacheValid($cacheName)){
            $interest = LC::getCache($cacheName);
        } else {
            $interest = $this->calcBriefInterest($userid, time());
            LC::cache($cacheName, $interest, SYS_CACHE_TIMEOUT);
        }
        $this->assign("interest" ,$interest);

        $type = 3;
        $this->assign("type", $type);
        if ($mobile == 0){
          $this->display("default/user");
        } else {
          $this->assign("mbl", 1);
          $this->display("default/user_m");
        }
    }

    public function rechargeAccount(){
        $type=4;
        $this->assign("type", $type);
        $this->display("default/user");
    }

    public function todayBidStat(){
        $type = 5;
        $this->assign("type", $type);
        $this->display("default/user");

    }

    public function listBasicBidInfo(){
        $userid=$this->isCookieValid();
        if(!$userid){
            $userid=$this->isUserLogin();
        }
        if(!$userid){
            $this->ppdLog("UserController/listBasicBidInfo: user didnot login.");
            return null;
        }

        $draw       = I('post.draw');
        $startPage  = I('post.start');
        $length     = I('post.length');
        $strategyid = I('post.strategyid');
        $qryStart   = date("Y-m-d", I('post.qrystart'));
        $qryEnd     = date("Y-m-d", (I('post.qryend') + 3600*24));

        $time = date('Y-m-d', strtotime('-3 day'));
        /* 如果查询的数据不是recent,即3天前的，则直接从bid表中取.
         * 由于bid表的索引是(BidId),(BidSn),(UserId, BidTime)，所以先按照索引查出数据,
         * 然后本地过滤, 减少RDS的计算量，加快访问速度.
         * */
        if($qryStart < $time){
            $bid = M('bid');
            $cdt   = "UserId={$userid} and BidTime > '{$qryStart}' and BidTime < '{$qryEnd}'";
            $field = array('BidTime','StrategyId','BidAmount','BidCost', 'ListingId');

            /* 必须添加上时间信息,否则查询不同的时间段,否则从cache取出的数据都是第一次查询的*/
            $cacheName = "basicBidInfo_{$userid}_{$qryStart}";
            if (true == LC::isCacheValid($cacheName)){
                $data = LC::getCache($cacheName);
            } else {
                $cnt      = $bid->where($cdt)->count('BidId');
                $pageSize = 1000;
                $pageCnt  = ceil($cnt/$pageSize);

                $data = array();
                $i    = 0;
                for($page = 0; $page < $pageCnt; $page++){
                    $start = $page * $pageSize;
                    $len   = $pageSize;
                    $set   = $bid->where($cdt)->field($field)->limit($start, $len)->order('BidTime desc')->select();
                    foreach($set as $val){
                        $data[$i] = $val;
                        $i++;
                    }
                }
                LC::cache($cacheName, $data, 600);
            }

            if($strategyid == -1){            /* 所有*/
                /* 啥都不做*/
            }else if ($strategyid == -2){     /* 所有系统: (0,1010] */
                foreach($data as $key => $val){
                    if($val['StrategyId'] > 1010){
                        unset($data[$key]);
                    }
                }
            }else if ($strategyid == -3){     /* 所有自定义: >100000*/
                foreach($data as $key => $val){
                    if($val['StrategyId'] <= 100000){
                        unset($data[$key]);
                    }
                }
            }else{  /*用户自定义: (1010, 100000]*/
                foreach($data as $key => $val){
                    if($val['StrategyId'] != $strategyid){
                        unset($data[$key]);
                    }
                }
            }
        } else {
            $bid = M("bid_recent");
            $cacheName  = "bidRecent";
            if (true == LC::isCacheValid($cacheName)){
                $dataArr = LC::getCache($cacheName);
                /* 根据条件从数组中取出对应的数据 */
                foreach($dataArr as $key => $val){
                    $vuid = $val['UserId'];
                    $vsid = $val['StrategyId'];
                    $vtm  = $val['BidTime'];

                    if($strategyid == -1){            /* 所有*/
                        $cdt = ($vuid == $userid) && ($vtm > $qryStart && $vtm < $qryEnd);
                    }else if ($strategyid == -2){     /* 所有系统 */
                        $cdt = ($vuid == $userid) && ($vsid > 0 && $vsid <=1010)  && ($vtm > $qryStart && $vtm < $qryEnd);
                    }else if ($strategyid == -3){     /* 所有自定义 */
                        $cdt = ($vuid == $userid) && ($vsid > 100000)  && ($vtm > $qryStart && $vtm < $qryEnd);
                    }else{
                        $cdt = ($vuid == $userid) && ($vsid == $strategyid) && ($vtm > $qryStart && $vtm < $qryEnd);
                    }

                    /* 删除数组中多余的字段 */
                    unset($val['UserId']);
                    if ($cdt){
                        $data[] = $val;
                    }
                }
            }else{
                if($strategyid == -1){            /* 所有*/
                    $strategyCdt = "";
                }else if ($strategyid == -2){     /* 所有系统 */
                    $strategyCdt = " and StrategyId > 0 and StrategyId <= 1010";
                }else if ($strategyid == -3){     /* 所有自定义 */
                    $strategyCdt = " and StrategyId >= 100000";
                }else{
                    $strategyCdt = " and StrategyId={$strategyid}";
                }
                $cdt   = "UserId={$userid}". $strategyCdt . " and BidTime > '{$qryStart}' and BidTime < '{$qryEnd}'";
                $field = array('BidTime','StrategyId','BidAmount','BidCost', 'ListingId');
                /* 考虑实时性，cache时间设置为5分钟，和bid_recent表更新的时间一致 */
                $data  = $bid->where($cdt)->field($field)->order('BidTime desc')->cache(true, 300)->select();
            }
        }

        $totalCnt        = count($data);
        $recordsTotal    = $totalCnt;
        $recordsFiltered = $totalCnt;
        $pageBid         = array_slice($data, $startPage, $length);

        $dt = $this->getDtContent($pageBid, 2);
        $dtRespond = array(
            "draw"            => intval($draw),
            "recordsTotal"    => intval($recordsTotal),
            "recordsFiltered" => intval($recordsFiltered),
            "data"            => $dt
        );
        echo json_encode($dtRespond);
    }

    /* 收益总体统计 */
    public function calcBriefInterest($userid, $time){
        $lpr   = M("lpr");
        $total = $lpr->where("UserId='{$userid}' and RepayInterest>0")->sum('RepayInterest');

        $interest = array();
        $interest['total'] = round($total, 2);

        /* 昨日收益的定义：[次日凌晨，今日凌晨), 特别注意区间开闭范围 */
        $yesterday   = date("Y-m-d", $time - 24 * 3600);
        $current     = date("Y-m-d", $time);
        $yesInterest = $lpr->where("UserId='{$userid}' and RepayDate>='{$yesterday}' and RepayDate<'{$current}'")
            ->sum('RepayInterest') + 0.0;
        $bdt = $this->calcBadDebt($userid, 1);
        $interest['yesterday'] = round($yesInterest - $bdt, 2);
        return $interest;
    }

    public function calcInterest($userid){
        /* 收益统计,默认是过去1年, 但不超过本系统开始运营的时间 */
        $lpr      = M("lpr");
        $interest = array();
        $overdue  = array();
        $stat     = array();
        $dueDayMin   = $lpr->where("UserId={$userid}")->field('DueDate')->min('DueDate');
        $scratchDay  = max(date("Y-m-d", strtotime($dueDayMin.' -30 day')), START_OF_SYS_DAY);

        for($i = 0; $i < OVERDUE_QUERY_DAYS; $i++){
            $cur   = $i;
            $end   = date("Y-m-d H:i:s",strtotime("-{$cur} day"));
            $start = date("Y-m-d H:i:s",strtotime($end . " -30 day"));

            if($end < $scratchDay)
                break;

            /* 延期条件：以30天为例，相对参考日期，到期时间在30天及其以前，并且(实际还款时间大于参考时间，
             * 或者，延期天数大于30天并且还款状态为等待还款)。
             * sql表达为：
             * $strSel = "UserId='{$userid}' and DueDate <='{$start}' and ((RepayDate>'{$end}')
             * or (OverdueDays > 30 and RepayStatus='0'))";
             * */
            $cdt['UserId']    = array('eq', $userid);
            $cdt['DueDate']   = array('elt', $start); //equal and less than
            $map['RepayDate'] = array('gt',$end);//greater than

            /* RepayStatus: 0：等待还款, 1：准时还款 2：逾期还款 3：提前还款 4：部分还款*/
            $map['_string']   = "OverdueDays > 30 and RepayStatus='0'";
            $map['_logic']    = 'OR';
            $cdt['_complex']  = $map;
            $sumOverdue = $lpr->where($cdt)->sum('OwingPrincipal') + 0.0;
            $overdue[substr($end, 0, 10)] = round($sumOverdue, 2);

            /* 毛收益 */
            unset($cdt);
            $cdt['UserId']    = array('eq', $userid);
            $cdt['RepayDate'] = array('elt', $end);
            $sumInterest = $lpr->where($cdt)->sum('RepayInterest') + 0.0;
            $interest[substr($end, 0, 10)]= round($sumInterest,2);

            /* 净收益*/
            $net = $interest[substr($end, 0, 10)] - $overdue[substr($end, 0, 10)];
            $netInterest[substr($end, 0, 10)] = round($net, 2);
        }

        $stat['interest']    = array_reverse($interest);
        $stat['overdue']     = array_reverse($overdue);
        $stat['netInterest'] = array_reverse($netInterest);
        return $stat;
    }

    public function fetchCumulatInterest($userid){
        $statMdl = M("stat_user");
        $rcdSet  = $statMdl->where("UserId={$userid}")
          ->field('UserId, BidDate, CumulatInterest, CumulatNetInterest,CumulatOverdueCapital')
          ->order('BidDate desc')->select();

        $lpr        = M("lpr");
        $dueDayMin  = $lpr->where("UserId={$userid}")->field('DueDate')->min('DueDate');
        $scratchDay = max(date("Y-m-d", strtotime($dueDayMin.' -30 day')), START_OF_SYS_DAY);

        for($i = 0; $i < OVERDUE_QUERY_DAYS; $i++){
            $cur  = $i;
            $end  = date("Y-m-d H:i:s",strtotime("-{$cur} day"));
            $today= date("Y-m-d H:i:s",strtotime("-0 day"));

            $date = $rcdSet[$i]['BidDate'];
            /*如果当天的数据还没有准备好，不做处理*/
            if($date > $today){
                continue;
            }
            /* 不处理该用户在系统中第一天以前的数据 */
           if($end < $scratchDay) {
                break;
            }

            $overdue[$date]     = round($rcdSet[$i]['CumulatOverdueCapital'], 2);
            $interest[$date]    = round($rcdSet[$i]['CumulatInterest'], 2);
            $netInterest[$date] = round($rcdSet[$i]['CumulatNetInterest'], 2);
        }

        /* 去掉无效值 */
        $maxOverdue = max($overdue);
        if($maxOverdue > 0){
            foreach($overdue as $key => $val){
                if(0 != $val){
                    break;
                }
                unset($overdue[$key]);
                unset($interest[$key]);
                unset($netInterest[$key]);
            }
        }

        $stat['interest']    = array_reverse($interest);
        $stat['overdue']     = array_reverse($overdue);
        $stat['netInterest'] = array_reverse($netInterest);
        return $stat;
    }

    /* function: 计算参考日期的收益和延期.
     * param: dayOffset:相对于今天的偏移天数，比如今天该值为0, 昨天该值为1;
     *        ovdDays:相对某个日期的延期天数;
     * */
    public function calcInterestByRefDate($userid, $refDayOffset, $ovdDays){
        $lpr        = M("lpr");
        $dueDayMin  = $lpr->where("UserId={$userid}")->field('DueDate')->cache(true, SYS_CACHE_TIMEOUT)->min('DueDate');
        $scratchDay = max(date("Y-m-d", strtotime($dueDayMin . " -{$ovdDays} day")), START_OF_SYS_DAY);

        $cur   = $refDayOffset;
        $end   = date("Y-m-d H:i:s",strtotime("-{$cur} day"));
        $start = date("Y-m-d H:i:s",strtotime($end . " -{$ovdDays} day"));

        if($end < $scratchDay)
            return null;

        /* 延期条件：以30天为例，相对参考日期，到期时间在30天及其以前，并且(实际还款时间大于参考时间，
         * 或者，延期天数大于30天并且还款状态为等待还款)。
         * */
        $cdt['UserId']    = array('eq', $userid);
        $cdt['DueDate']   = array('elt', $start); //equal and less than
        $map['RepayDate'] = array('gt',$end);//greater than
        /* RepayStatus: 0：等待还款, 1：准时还款 2：逾期还款 3：提前还款 4：部分还款*/
        $map['_string']   = "OverdueDays > {$ovdDays} and RepayStatus='0'";
        $map['_logic']    = 'OR';
        $cdt['_complex']  = $map;
        $sumOverdue = $lpr->where($cdt)->sum('OwingPrincipal') + 0.0;
        $overdue = round($sumOverdue, 2);

        /* 毛收益 */
        unset($cdt);
        $cdt['UserId']    = array('eq', $userid);
        $cdt['RepayDate'] = array('elt', $end);
        $sumInterest = $lpr->where($cdt)->sum('RepayInterest') + 0.0;
        $interest = round($sumInterest,2);

        /* 净收益*/
        $net = $interest - $overdue;
        $netInterest = round($net, 2);

        $stat['interest']    = $interest;
        $stat['overdue']     = $overdue;
        $stat['netInterest'] = $netInterest;
        return $stat;
    }

    /* function: 计算参考日期的坏账金额. 坏账定义：超过90天及其以上而未归还的金额
     * param: dayOffset:相对于今天的偏移天数，比如今天该值为0, 昨天该值为1;
     * */
    public function calcBadDebt($userid, $dayOffset){
        $lpr = M('lpr');
        /* 以某天凌晨作为参考日期 */
        $end    = date("Y-m-d",strtotime("-{$dayOffset} day"));
        $start  = date("Y-m-d",strtotime($end . " -89 day")); /*90天*/
        /* RepayStatus: 0：等待还款, 1：准时还款 2：逾期还款 3：提前还款 4：部分还款*/
        $cdt     = "UserId={$userid} and DueDate>='{$start}' and OverdueDays='90' and RepayStatus='0'";
        $owe     = $lpr->where($cdt)->sum('OwingPrincipal') + 0.0;
        $badDebt = round($owe, 2);
        return $badDebt;
    }

    public function ajaxStatInterest(){
        $userid=$this->isCookieValid();
        if(!$userid){
            $userid=$this->isUserLogin();
        }
        if(!$userid){
            $this->ppdLog("UserController/ajaxStatInterest: user didnot login.");
            return null;
    }

        $cacheName = "statInterest_{$userid}";
        if (true == LC::isCacheValid($cacheName)){
            $data = LC::getCache($cacheName);
        }else{
            //不实时计算
            //$data = $this->calcInterest($userid);
            $data = $this->fetchCumulatInterest($userid);
            LC::cache($cacheName, $data, SYS_CACHE_TIMEOUT);
        }
        $ajax = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->ajaxReturn($ajax);
    }

    public function listInterest(){
        $userid=$this->isCookieValid();
        if(!$userid){
            $userid=$this->isUserLogin();
        }
        if(!$userid){
            $this->ppdLog("UserController/listInterest: user didnot login.");
            return null;
        }

        $draw      = I('post.draw');
        $startPage = I('post.start');
        $length    = I('post.length');

        $cacheName = "interestList_{$userid}";
        if (true == LC::isCacheValid($cacheName)){
            $data = LC::getCache($cacheName);
        } else {
            $data = $this->queryInterestList($userid);
            LC::cache($cacheName, $data, BID_DATA_CACHE_TIME);
        }

        $totalCnt        = count($data);
        $recordsTotal    = $totalCnt;
        $recordsFiltered = $totalCnt;
        $pageBid = array_slice($data, $startPage, $length);
        $dt      = $this->getDtContent($pageBid, 2);
        $dtRespond = array(
            "draw"            => intval($draw),
            "recordsTotal"    => intval($recordsTotal),
            "recordsFiltered" => intval($recordsFiltered),
            "data"            => $dt
        );
        echo json_encode($dtRespond);
    }

    public function queryInterestList($userid){
        $lprMdl        = M("lpr");
        $threeMonthAgo = date("Y-m-d", strtotime("-30 day"));
        $cdt   = "UserId='{$userid}' and RepayInterest>0 and RepayDate>'{$threeMonthAgo}'";
        $field = array('ListingId','StrategyId','RepayDate','RepayPrincipal', 'RepayInterest','OrderId');
        $data  = $lprMdl->where($cdt)->field($field)->order('RepayDate desc')->cache(true, BID_DATA_CACHE_TIME)->select();
        return $data;
    }

    public function calcDelayRatio($uid, $day, $phase, $strategyid){
        $lpr      = M("lpr");
        $dayCdt   = ($day == -1) ? "" : " and OverdueDays>={$day}";
        $phaseCdt = ($phase == -1) ? "" : " and OrderId={$phase}";
        $firstPhase = " and OrderId=1";

        if($day != -1 && $phase != -1){
            $end       = date("Y-m-d",strtotime("-{$day} day"));
            $endDayCdt =" and DueDate <='{$end}'";
        }else{
            $endDayCdt ="";
        }

        if($strategyid == -1){            /* 所有*/
            $strategyCdt = "";
        }else if ($strategyid == -2){     /* 所有系统信用标 */
            $strategyCdt = " and StrategyId >= 10 and StrategyId < 1000 and status >= 0";
        }else if ($strategyid == -3){     /* 所有自定义 */
            $strategyCdt = " and StrategyId>=100000";
        }else{
            $strategyCdt = " and StrategyId={$strategyid}";
        }

        $delayQueryStr = "UserId={$uid}" . $strategyCdt . $dayCdt . $phaseCdt . " and RepayStatus=0";
        $totalDelayCnt = $lpr->where($delayQueryStr)->field('ListingId')->cache(true, LPR_QUERY_TIMEOUT)->count();
        $totalQueryStr = "UserId={$uid}" . $strategyCdt . $phaseCdt . $endDayCdt;
        $totalCnt      = $lpr->where($totalQueryStr)->field('ListingId')->cache(true, LPR_QUERY_TIMEOUT)->count();

        $delayRatio    = round($totalDelayCnt * 100 / ($totalCnt + 0.001), 2);
        /*如果数量太少就不显示*/
        if($totalCnt < 100){
            $delayRatio = 0;
        }
        return $delayRatio;
    }

    public function calcDelayRatioByDay($uid, $day) {
        if($uid < 0){
            $this->ppdLog("Statistic/calcDelayRatio: userid({$uid}) is invalid", 3);
            return INVALID_DELAY_RATIO;
        }

        $lpr           = M("lpr");
        $ago           = date("Y-m-d H:i:s", time() - 3600 * 24 * $day);
        $selStr        = "UserId=" . $uid . " and RepayInterest>0";
        $totalRepayCnt = $lpr->where($selStr)->field('ListingId')->count();

        $selStr        = "UserId=" . $uid . " and OverdueDays>" . $day ." and RepayStatus=0 and UpdateTime >'{$ago}'";
        $totalDelayCnt = $lpr->where($selStr)->field('ListingId')->count();
        $delayRatio    = round($totalDelayCnt * 100 / ($totalRepayCnt + 0.001), 2);

        $this->ppdLog("Statistic/calcDelayRatioByDay:user{$uid}'s totalRepayCnt = {$totalRepayCnt}," .
            "totalDelayCnt = {$totalDelayCnt} in {$day} days");
        return $delayRatio;
    }

    public function calcDelayRatioByPhase($uid, $phase) {
        if($uid < 0 || !in_array($phase, range(1, 6, 1))){
            $this->ppdLog("Statistic/calcDelayRatio: userid({$uid}) or phase({$phase}) is invalid", 3);
            return INVALID_DELAY_RATIO;
        }

        $lpr        = M("lpr");
        $selStr     = "UserId=" . $uid . " and OrderId={$phase} and OverdueDays>0";
        $selStr     = "UserId=" . $uid . " and OrderId={$phase}";
        $totalCnt   = $lpr->where($selStr)->count();
        $delayRatio = round($delayCnt * 100 / ($totalCnt + 0.001), 2);

        $this->ppdLog("Statistic/calcDelayRatioByPhase:user{$uid}'s delayCnt = {$delayCnt}," .
            "totalCnt = {$totalCnt} in {$phase} phase");
        return $delayRatio;
    }

    public function statOverdue($period=30, $strategy=-1){
        $userid = $this->isCookieValid();
        if(!$userid){
            $userid=$this->isUserLogin();
        }
        if(!$userid){
            $this->ppdLog("UserController/statOverdue: user didnot login.");
            return null;
        }

        if(empty($period)){
            $period = -1;
        }
        if(empty($strategy)){
            $strategy = -1;
        }

        $isCalcRealtime = false;
        if($isCalcRealtime){
            $overdueDays = $period;
            for($phase = 0; $phase < 12; $phase++){
                $pa = $phase + 1;
                $ovd["{$pa}期"] = $this->calcDelayRatio($userid, $overdueDays, $phase, $strategy);
            }
        } else {
            $ovdPhase = $this->fetchOverdue($userid, $period, $phase, $strategy);
            for($phase = 0; $phase < 12; $phase++){
                $pa = $phase + 1;
                $ovd["{$pa}期"] = round($ovdPhase["PhaseOverdue{$pa}"],2);
            }
        }
        return $ovd;
    }

    public function fetchOverdue($userid, $period, $phase, $strategy){
        $op  = M('stat_overdue_phase');
        $cdt = "UserId={$userid} and Period={$period} and StrategyId={$strategy}";
        $ovd = $op->where($cdt)->select();
        return $ovd[0];
    }

    public function ajaxStatOverdue(){
        //$input = file_get_contents("php://input");
        $period   = I('post.period');
        $strategy = I('post.strategy');

        $data = $this->statOverdue($period, $strategy);
        $ajax = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->ajaxReturn($ajax);
    }

    /* dateAttr: 0:今天; 1:历史(今天以前) */
    public function calcBidDist($userid, $dateAttr=1){
        if(0 == $dateAttr){
            /* 对今天的统计 */
            /* 只能直接从缓存中取出由remoteController计算好的数据 */
            $cacheName  = "bidLoanRecent";
            if (true == LC::isCacheValid($cacheName)){
                $cacheData = LC::getCache($cacheName);
            } else {
                return null;
            }

            $data = array_filter($cacheData, function($a) use ($userid){
                return ($a['UserId'] == $userid)? true:false;
            });
            $bidDist = $this->performBidDistCalc($data, 0);
        } else {
            /*
              $bidjoin = $bid->join('JOIN ppd_history_loan ON ppd_history_loan.ListingId=ppd_bid.ListingId')
              ->where("UserId={$userid}")->field('CreditCode,EducationDegree,Age')
              ->cache(true, BID_DATA_CACHE_TIME)->select();
             */
            $bid = M('bid');
            $data = $bid->table('ppd_bid as a')->join('ppd_history_loan as b ON a.ListingId=b.ListingId')
                ->where("UserId={$userid} and BidTime>'2018-01-01'")
                ->field('a.BidId, a.UserId, a.ListingId, a.StrategyId, b.CreditCode, b.EducationDegree, b.Age')
                ->cache(true, SYS_CACHE_TIMEOUT)->select();
            /* 1:百分化 */
            $bidDist = $this->performBidDistCalc($data, 1);
        }
        return $bidDist;
    }

    /* 计算投标分布，包括风险等级,策略分布,学历分布 */
    public function performBidDistCalc($dataArr, $isPercentify){
        if(empty($dataArr)){
            return null;
        }

        $nameTbl = $this->getStrategyNameTbl();
        $cnt = 0;
        foreach($dataArr as $rcd){
            if ($cnt > 15000){
                break;
            }

            /* 魔镜等级分布统计 */
            if($rcd['CreditCode'] == 'AA'){
                $risk['AA']++;
            }else if($rcd['CreditCode'] == 'A'){
                $risk['A']++;
            }else if($rcd['CreditCode'] == 'B'){
                $risk['B']++;
            }else if($rcd['CreditCode'] == 'C'){
                $risk['C']++;
            }else if($rcd['CreditCode'] == 'D'){
                $risk['D']++;
            }else if($rcd['CreditCode'] == 'E'){
                $risk['E']++;
            }else if($rcd['CreditCode'] == 'F'){
                $risk['F']++;
            }else {
                /* 有可能history_loan表中没有统计到bid表中对应的某个ListingId信息,
                 * 数据就会为null, 归类为unknown */
                if (0 == $isPercentify){
                    $risk['未知']++;
                }
            }

            /* 学历分布统计 */
            /* [1] NA               "本科"         "专科"         "专科(高职)"         "专科（高职）"
             * [6] "专升本"         "硕士研究生"   "博士研究生"   "夜大电大函大普通班" "硕士"
             * [11] "第二学士学位"  "高升本"
             */
            if($rcd['EducationDegree'] == '博士研究生'){
                $eduDegree['博士']++;
            }else if(($rcd['EducationDegree'] == '硕士研究生') || ($rcd['EducationDegree'] == '硕士')){
                $eduDegree['硕士']++;
            }else if($rcd['EducationDegree'] == '本科'){
                $eduDegree['本科']++;
            }else if($rcd['EducationDegree'] == '专科'){
                $eduDegree['专科']++;
            }else if($rcd['EducationDegree'] == null){
                if (0 == $isPercentify){
                    $eduDegree['未知']++;
                }
            }else{
                $eduDegree['无学历']++;
            }

            /* 年龄分布统计 */
            if(($rcd['Age'] <= 20) && ($rcd['Age'] > 0)){
                $age['10-20']++;
            }else if($rcd['Age'] <= 30){
                $age['20-30']++;
            }else if($rcd['Age'] <= 40){
                $age['30-40']++;
            }else if($rcd['Age'] > 40){
                $age['40-100']++;
            }else{
                if (0 == $isPercentify){
                    $age['未知']++;
                }
            }

            /* 策略分布统计 */
            $strategyId = $rcd['StrategyId'];
            if(!is_null($strategyId)){
                $name    = $nameTbl[$strategyId];
                $strategy[$name]++;
            }

            $cnt++;
        }

        /* 转化成百分比 */
        if(1 == $isPercentify){
            /* 注意这里传入引用 */
            $func = function(&$v, $k, $c){
                $v = round(100 * intval($v)/$c, 1);
            };

            array_walk($risk,      $func, array_sum($risk));
            array_walk($eduDegree, $func, array_sum($eduDegree));
            array_walk($age,       $func, array_sum($age));
            array_walk($strategy,  $func, array_sum($strategy));

            arsort($strategy);
            $cnt = 0;
            foreach($strategy as $key => $val){
                $cnt++;
                $sum += $val;
                /* 如果大于90, 不再累加 */
                if (($sum > 90) && ($cnt < count($strategy))){
                    break;
                } else {
                    $strategyT[$key] = $val;
                }
            }
            if($cnt < count($strategy)){
                $strategyT['其他'] = 100 - $sum;
            }
            arsort($strategyT);
            $strategy = $strategyT;
        }

        $dist['risk'] = $risk;
        $dist['eduDegree'] = $eduDegree;
        $dist['age'] = $age;
        $dist['strategy'] = $strategy;
        return $dist;
    }

    public function ajaxStatBidDist(){
        $userid=$this->isCookieValid();
        if(!$userid){
            $userid=$this->isUserLogin();
        }
        if(!$userid){
            $this->ppdLog("UserController/statOverdue: user didnot login.");
            return null;
        }

        /* 如果缓存有效，则直接从缓存取出，否则重新计算，再写入缓存 */
        $cacheName = "bidDist_{$userid}";
        if (true == LC::isCacheValid($cacheName)){
            $data = LC::getCache($cacheName);
        } else {
            /* 1: 历史(昨天及其天以前) */
            $data = $this->calcBidDist($userid, 1);
            if(!is_null($data)){
                LC::cache($cacheName, $data, BID_DATA_CACHE_TIME);
            }
        }

        $ajax = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->ajaxReturn($ajax);
    }

    public function ajaxStatBidDistToday(){
        $userid=$this->isCookieValid();
        if(!$userid){
            $userid=$this->isUserLogin();
        }
        if(!$userid){
            $this->ppdLog("UserController/statOverdueToday: user didnot login.");
            return null;
        }

        /* 如果缓存有效，则直接从缓存取出，否则重新计算，再写入缓存 */
        $cacheName = "bidDistTdy_{$userid}";
        if (true == LC::isCacheValid($cacheName)){
            $data = LC::getCache($cacheName);
        } else {
            /* 0: 今天 */
            $data = $this->calcBidDist($userid, 0);
            if(!is_null($data)){
                LC::cache($cacheName, $data, BID_DATA_CACHE_TIME);
            }
        }

        if (empty($data)){
          $data['risk']      = ['魔镜等级' => 0];
          $data['eduDegree'] = ['学历' => 0];
          $data['age']       = ['年龄' => 0];
          $data['strategy']  = ['策略' => 0];
        }

        $ajax = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->ajaxReturn($ajax);
    }

    /* perid: 日:1 ，周:2，月:3;
     * dataArr: 0:今天, 1: 历史(昨天及其以前)
     */
    public function calcBidAmount($userid, $period, $dateAttr=1){
        if(0 == $dateAttr){
            $bid      = M("bid_recent");
            $tdyBreak = date("Y-m-d", time());
            $userbid  = $bid->where("UserId={$userid} and BidTime>='{$tdyBreak}'")
                ->field('BidId,BidTime')->select();

            $userBidAmount = array();
            $amt['00:00'] = 0;
            /* perid: 30min:1, 60min:2 */
            if($period == 1){
                foreach($userbid as $rcd){
                    $time   = date("H:i:s", strtotime($rcd['BidTime']));
                    $hour   = date("H", strtotime($rcd['BidTime']));
                    $minite = date("i", strtotime($rcd['BidTime']));
                    if($minite < '30'){
                        $ml = '00';
                        $mr = '30';
                    } else if($minite < '60'){
                        $ml = '30';
                        $mr = '60';
                    }

                    $left  = $hour.":" . $ml;
                    $right = $hour.":" . $mr;
                    if($time >= $left && $time < $right){
                        $amt[$left]++;
                    }
                }

            }else if($period ==2){
                /* 待实现 */
            }else if($period ==3){
                /* 待实现 */
            }
            ksort($amt);

            /* 计算出今日累计投标数 */
            $sum = 0;
            foreach($amt as $key=>$val){
                $sum += $val;
                $amtCumu[$key] = $sum;
            }

            $bidAmount = array('amtPulse' => $amt, 'amtCumu' => $amtCumu);
        } else if(1 == $dateAttr){
            $bid      = M("bid");
            $tdyBreak = date("Y-m-d", time());
            $threeMonthAgo = date("Y-m-d", strtotime("-180 day"));
            $userbid  = $bid->where("UserId={$userid} and BidTime>'{$threeMonthAgo}' and BidTime<'{$tdyBreak}'")
                ->field('BidId,BidTime')->select();

            $userBidAmount = array();
            if($period == 1){
                foreach($userbid as $rcd){
                    $day = substr($rcd['BidTime'], 0, 10);
                    $userBidAmount[$day]++;
                }
            }else if($period ==2){
                /* 待实现 */
            }else if($period ==3){
                /* 待实现 */
            }
            $bidAmount = array('day' => $userBidAmount);
        }
        return $bidAmount;
    }

    public  function ajaxBidAmount(){
        $userid=$this->isCookieValid();
        if(!$userid){
            $userid=$this->isUserLogin();
        }
        if(!$userid){
            $this->ppdLog("UserController/ajaxBidAmount: user didnot login.");
            return null;
        }

        $cacheName = "bidAmount_{$userid}";
        if (true == LC::isCacheValid($cacheName)){
            $data = LC::getCache($cacheName);
        } else {
            /* 1: 以天为周期 */
            $data = $this->calcBidAmount($userid, 1, 1);
            LC::cache($cacheName, $data, SYS_CACHE_TIMEOUT);
        }

        $ajax = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->ajaxReturn($ajax);
    }

    public  function ajaxBidAmountToday(){
        $userid=$this->isCookieValid();
        if(!$userid){
            $userid=$this->isUserLogin();
        }
        if(!$userid){
            $this->ppdLog("UserController/ajaxBidAmount: user didnot login.");
            return null;
        }

        $cacheName = "bidAmountToday";
        if (true == LC::isCacheValid($cacheName)){
            $data = LC::getCache($cacheName);
        } else {
            /* 1:周期为30分钟 */
            $data = $this->calcBidAmount($userid, 1, 0);
        }

        $ajax = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->ajaxReturn($ajax);
    }

    private function getDtContent($pageRcd, $strategyTypePos){
        $dt = array();
        $nameTbl = $this->getStrategyNameTbl();
        for($i = 0; $i < count($pageRcd); $i++) {
            unset($pageData);
            $elemCnt = 0;
            foreach($pageRcd[$i] as $key => $val){
                if($key == "StrategyId"){
                    $id  = $pageRcd[$i]['StrategyId'];
                    $val = $nameTbl[$id];
                }
                $pageData[] = $val;
                $elemCnt++;

                /* 等到一个记录处理完成后，将投标类型插入进数组 */
                if($elemCnt == count($pageRcd[$i])){
                    $sid  = $pageRcd[$i]['StrategyId'];
                    $type = $this->getStrategyTypeById($sid);
                    array_splice($pageData, $strategyTypePos, 0, $type);
                }
            }
            $dt[] = array_values($pageData);
        }
        return $dt;
    }

    public function getStrategyNameTbl(){
        $cacheName = "strategyName";
        if (true == LC::isCacheValid($cacheName)){
            $cache = LC::getCache($cacheName);
            return $cache;
        }

        $strategyMdl    = M('strategy');
        $strategyTbl    = $strategyMdl->select();
        $strategyPsnMdl = M('personal_strategy');
        $strategyPsnTbl = $strategyPsnMdl->select();
        $nameArr    = [];
        $namePsnArr = [];
        for($i = 0; $i < count($strategyTbl); $i++){
            $key = $strategyTbl[$i]['StrategyId'];
            $val = $strategyTbl[$i]['Name'] ;
            $nameArr[$key] =  strval($val);
        }
        for($i = 0; $i < count($strategyPsnTbl); $i++){
            $key = $strategyPsnTbl[$i]['StrategyId'];
            $val = $strategyPsnTbl[$i]['StrategyName'] ;
            $namePsnArr[$key] = strval($val);
        }
        $strategyNameArr = $nameArr + $namePsnArr;
        LC::cache($cacheName, $strategyNameArr, SYS_CACHE_TIMEOUT);
        return $strategyNameArr;
    }

    public function getStrategyTypeById($strategyId){
        if(intval($strategyId) < 1000){
            $type = "系统";
        }else if(intval($strategyId) > 100000){
            $type = "自定义";
        }else{
            $type = "赔标";
        }
        return $type;
    }

    public function downloadBidData(){
        $userid=$this->isCookieValid();
        if(!$userid){
            $userid=$this->isUserLogin();
        }
        if(!$userid){
            $this->ppdLog("UserController/downloadBidData: user didnot login.");
            return null;
        }

        $bid         = M("bid");
        $condition   = "UserId={$userid} and BidTime>'2018-01-01'";
        $bidToExport = $bid->where($condition)->field('BidTime, StrategyId, BidAmount, BidCost, ListingId')
            ->order('BidTime desc')->select();
        if (0 == count($bidToExport)){
            return false;
        }

        $range = array('A', 'B', 'C', 'D', 'E', 'F');
        $title = array('投标时间', '投标策略', '策略类型','投标金额(元)','投标费用(雕币)', '投标号');
        $data  = $this->getDtContent($bidToExport, 2);

        $excelData = array();
        $excelData['headerRange'] = $range;
        $excelData['headerTitle'] = $title;
        $excelData['dt'] = $data;
        $maxCnt = 1000;
        $this->toExcel($excelData, $maxCnt);
        return;
    }

    public function downloadInterestList(){
        $userid=$this->isCookieValid();
        if(!$userid){
            $userid=$this->isUserLogin();
            $this->ppdLog("user/downloadInterestList: user didnot login");
            return;
        }

        $lpr       = M("lpr");
        $condition = "UserId={$userid} and RepayInterest>0";
        $field     = array('ListingId','StrategyId','RepayDate','RepayPrincipal', 'RepayInterest','OrderId');
        $toExport  = $lpr->where($condition)->field($field)->order('RepayDate desc')->select();
        if (0 == count($toExport)){
            $this->ppdLog("user/downloadInterestList: nothing to download");
            return;
        }

        $range = array('A', 'B', 'C', 'D', 'E', 'F', 'G');
        $title = array('投标号', '投标策略', '策略类型','还款时间','本金', '收益', '还款期号');
        $data  = $this->getDtContent($toExport, 2);

        $excelData = array();
        $excelData['headerRange'] = $range;
        $excelData['headerTitle'] = $title;
        $excelData['dt'] = $data;
        $maxCnt = 1000;
        $this->toExcel($excelData, $maxCnt);
        return;
    }

    public function toExcel($excelData, $exportCnt){
        Vendor('PHPExcel.PHPExcel');
        $excel =  new \PHPExcel();

        /* 填充表头信息 */
        $range  = $excelData['headerRange'];
        $title  = $excelData['headerTitle'];
        $maxCnt = $exportCnt;

        /* 填充excel对象表格头 */
        for ($col = 0; $col < count($title); $col++) {
            $excel->getActiveSheet()->setCellValue("$range[$col]1", "$title[$col]");
        }

        $data  = $excelData['dt'];
        /* 填充excel对象表格内容 */
        for ($row = 2; $row < count($data) + 2; $row++) {
            $col = 0;
            foreach ($data[$row - 2] as $key => $value) {
                $excel->getActiveSheet()->setCellValue("$range[$col]$row", "$value");
                $col++;
            }
            if($row >= ($maxCnt))
                break;
        }
        /* 生成随机文件名 */
        $chars   = '0123456789';
        $length  = 12;
        $randstr = '';
        for ( $i = 0; $i < $length; $i++ ) {
            $randstr .= $chars[ mt_rand(0, strlen($chars) - 1) ];
        }
        $filename = "{$randstr}.xls";
        /* 导出excel */
        $write = new \PHPExcel_Writer_Excel5($excel);
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header("Content-Disposition:attachment;filename={$filename}");
        header("Content-Transfer-Encoding:binary");
        $write->save('php://output');
        return;
    }


    public function isUserExist($uid)
    {
        $um = M("user");
        return $um->where("UserId = '{$uid}'")->find();
    }

    public function updateJdcode(){
        $jdcode = I('jdcode');
        if($jdcode){
            $userid=$this->isCookieValid();
            if(!$userid){
                $userid=$this->isUserLogin();
            }
            if(!$userid){
                echo "登录超时";
            }else{
                $jduid = hexdec($jdcode)-500000;
                if($jduid == $userid)
                    echo "优惠码不能是自己的";
                else{
                    if ($this->isUserExist($jduid)){
                        cookie('jdcode',$jdcode,3600*24*15);
                        $um = M("user");
                        $data['UserId'] = $userid;
                        $data['JdCode'] = $jdcode;
                        if($um->where("UserId = '{$userid}'")->save($data))
                            echo "OK";
                        else
                            echo "优惠码保存失败，请联系管理员！";
                    }else{
                        echo "优惠码不存在";
                    }
                }
            }
        }else{
            echo "OK";
        }
    }

    public function promotion(){
        $userid=$this->isCookieValid();
        if(!$userid){
            $userid=$this->isUserLogin();
        }
        if(!$userid){
            $this->display("default/login");
        }else{
            $jdcode = dechex($userid + 500000);
            $this->assign("jdcode",$jdcode);
            $this->display("default/promotion");
        }
    }

    public function readOvdStat($type){
        $userid=$this->isCookieValid();
        if(!$userid){
            $userid=$this->isUserLogin();
        }
        if(!$userid){
            $this->ppdLog("UserController/readOvdStat: user didnot login.");
            return null;
        }

        if($type == 'stg') {
            $fn = __DIR__."/data/uid".$userid."_tbl_ovd_stg.json";
        }
        else if ($type == 'mth') {
            $fn = __DIR__."/data/uid".$userid."_tbl_ovd_mth.json";
        }

        $data['content'] = json_decode(file_get_contents($fn), true);
        $data['time'] = date("Y-m-d H:i:s", filemtime($fn));
        $this->ajaxReturn($data);
    }


    public function testStrategyName(){
        $tbl = $this->getStrategyNameTbl();
        for($i = 1; $i < 33; $i++)
            echo "$i: {$tbl[$i]}</br>";
        for($i = 1000; $i<= 1010; $i++){
            echo "$i: {$tbl[$i]}</br>";
        }
        for($i = 100000; $i <= 100724; $i++){
            echo "$i: {$tbl[$i]}</br>";
        }
    }
    public function testBadDebt(){
        for($i=0;$i<90;$i++){
            $bad = $this->calcBadDebt(926, $i);
            echo "i=".$i.":".$bad."</br>";

        }
    }

    public function testCalcInterest(){
      $userid = array(1,2, 97,885,920,926);
      foreach($userid as $u){
        for($offset = 0; $offset < 60; $offset++){
          $userid       = $u;
          $refDayOffset = $offset;
          $ovdDays  = 30;
          $interest = $this->calcInterestByRefDate($userid, $refDayOffset, $ovdDays);
          echo "userid:{$userid}";
          var_dump($interest);
        }
      }
    }

    public function testStatOverdue(){
        $period = array(30, 90);
        $strategy = array(-1, -2,-3);
        foreach($period as $p){
            foreach($strategy as $s){
                $res = $this->statOverdue($p,$s);
                echo "period:".$p.", strategy:".$s;
                var_dump($res);
            }
        }
    }

    public function testfetchUserBidInfo(){
      $userid = array(1, 2, 97, 885, 920, 926);
      foreach($userid as $u){
          $info = $this->fetchUserBidInfo($u);
          echo "userid:{$u}";
          var_dump($info);
        }
    }

}

