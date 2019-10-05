<?php
namespace Home\Controller;
use Think\Controller;
/* 第三方类库 */
use Stash\Pool;
use Stash;

/**
 * 基础控制器
 */
class BaseController extends Controller {
    
    /**
     * php在生成密钥时，需要选择PEM PKCS#1格式的密钥，否则会报错
     * @var string $appPrivateKey
     */
    protected $appid_paizhitou = " ";
    private $private_key_paizhitou = "";
    protected $appid_stockplayer = " ";
    private $private_key_stockplayer = "";
    protected $appid_p2phunter = " ";
    private $private_key_p2phunter =" ";
    private $public_client_key_p2phunter ="";
    //18516292739 老司机账号 下
    protected $appid_laosiji = "";
    protected $private_key_laosiji = "";
    protected $appid_wangdailieshou = "";
    protected $private_wangdailieshou = "";
    protected $appid_n5 = "";
    protected $private_n5 = "";
    /* 拍拍助手小程序*/
    protected $appid_ppzhushou = "";
    protected $private_ppzhushou = "";
    private $appPublicKey;
    private $appPrivateKey;
    protected $appPrivateKey_paizhitou;
    protected $appPrivateKey_p2phunter;
    protected $appPrivateKey_stockplayer;
    protected $appPrivateKey_wangdailieshou;
    protected $appPrivateKey_laosiji;
    protected $appPrivateKey_n5;
    protected $appPrivateKey_ppzhushou;
    
    protected $charge_start_time;
    protected $appid;
    protected $exlock;
    protected $cost_rate = 15;
    protected $cost_rate_sys = 20;
    protected $max_diy_count = 10;
    protected $vip = array(885,1697,926,726);

    
    public function __construct(){
        parent::__construct();
        $this->initHeader();
        //初始化系统信息
        $str_paizhitou = chunk_split($this->private_key_paizhitou, 64, "\n");
        $str_p2phunter = chunk_split($this->private_key_p2phunter, 64, "\n");
        $str_stockplayer = chunk_split($this->private_key_stockplayer, 64, "\n");
        $str_p2phunter = chunk_split($this->private_key_p2phunter, 64, "\n");
        $str_stockplayer = chunk_split($this->private_key_stockplayer, 64, "\n");
        $str_laosiji = chunk_split($this->private_key_laosiji, 64, "\n");
        $str_wangdailieshou = chunk_split($this->private_wangdailieshou, 64, "\n");
        $str_n5 = chunk_split($this->private_n5, 64, "\n");
        $str_ppzhushou = chunk_split($this->private_ppzhushou, 64, "\n");
        
        
        $this->appid=$this->appid_p2phunter;
        $this->str = chunk_split($this->private_key_p2phunter, 64, "\n");
        $this->publicstr=chunk_split($this->public_client_key_p2phunter, 64, "\n");
        
        //处理私钥
        $this->appPrivateKey = "-----BEGIN RSA PRIVATE KEY-----\n{$this->str}-----END RSA PRIVATE KEY-----\n";
        $this->appPrivateKey_paizhitou = "-----BEGIN RSA PRIVATE KEY-----\n{$str_paizhitou}-----END RSA PRIVATE KEY-----\n";
        $this->appPrivateKey_p2phunter = "-----BEGIN RSA PRIVATE KEY-----\n{$str_p2phunter}-----END RSA PRIVATE KEY-----\n";
        $this->appPrivateKey_stockplayer = "-----BEGIN RSA PRIVATE KEY-----\n{$str_stockplayer}-----END RSA PRIVATE KEY-----\n";
        $this->appPrivateKey_laosiji = "-----BEGIN RSA PRIVATE KEY-----\n{$str_laosiji}-----END RSA PRIVATE KEY-----\n";
        $this->appPrivateKey_wangdailieshou = "-----BEGIN RSA PRIVATE KEY-----\n{$str_wangdailieshou}-----END RSA PRIVATE KEY-----\n";
        $this->appPrivateKey_n5 = "-----BEGIN RSA PRIVATE KEY-----\n{$str_n5}-----END RSA PRIVATE KEY-----\n";
        $this->appPrivateKey_ppzhushou = "-----BEGIN RSA PRIVATE KEY-----\n{$str_ppzhushou}-----END RSA PRIVATE KEY-----\n";
        
        $this->appPublicKey = "-----BEGIN PUBLIC KEY-----\n{$this->publicstr}-----END PUBLIC KEY-----\n";
        $this->charge_start_time=strtotime("11/01/17");
    }
    
    public function isVip($uid)
    {
        foreach ($this->vip as $id)
        {
            if($id==$uid)
                return true;
        }
        return false;
    }
    public function initHeader(){
        // 导入Image类库
        $this->assign('title',"金雕猎手 - 拍拍贷第三方自动投标软件");
        //$this->assign('navi_item2',"用户中心");
        //$this->assign('navi_item3',"策略设置");
        //$this->assign('navi_item4',"数据分析");
        //$this->assign('navi_item5',"常见问题");
        //$this->assign('navi_item6',"关于我们");

        $navItems = array();
        $navItems[0]=array('url'=> __ROOT__."/home/user", 'title'=>'用户中心' );
        $navItems[1]=array('url'=> __ROOT__."/home/strategy", 'title'=>'策略设置 ');
        $navItems[2]=array('url'=> __ROOT__."/home/user/multiauth", 'title'=>'授权设置' );
        $navItems[3]=array('url'=> __ROOT__."/home/analysis", 'title'=>'数据分析' );
        //$navItems[2]=array('url'=> "#", 'title'=>'策略回溯' );
        //$navItems[3]=array('url'=> "#", 'title'=>'行情监控' );
        //$navItems[4]=array('url'=> "#", 'title'=>'投资干货' );
        $navItems[4]=array('url'=> __ROOT__."/home/help", 'title'=>'常见问题' );
        $navItems[5]=array('url'=> __ROOT__."/home/about", 'title'=>'关于我们' );
        $this->assign('navItems', $navItems);
    }
    /**
     * 空操作处理
     */
    public function _empty($name){
        echo("你的思想太跳跃，系统完全跟不上....");
    }
    /**
     * 如果登录则返回        UserId, 否则返回false;
     */
    public function cookiewr($uid=2)
    {
        cookie('uid',$uid,3600*24);
    }
    public function cookierd()
    {
        $id=cookie('oid');
        print_r($_COOKIE);
        echo "id:" .$id;
    }
    public function isCookieValid()
    {
        $uid = cookie('uid');
        $time = cookie('time');
        $sid = cookie('sid');
        $ip = $_SERVER['REMOTE_ADDR'];
        if(!empty($uid) && is_numeric($uid)){
            $m=M("User");
            $data=$m->where("UserId='{$uid}'")->cache(true, 10)->find();
            if($data){
                $oid=$data['OpenID'];
                if($sid == md5($oid . $uid . $time . $ip .$oid) && time()-$time<3600*24)
                {
                    return $uid;
                }else{ 
                    //$this->ppdLog("isCookieValid:" . $uid . "login validate failed!");
                }
            }else{
                //$this->ppdLog("isCookieValid:" . $uid . "not found.");
            }
        }else{
            //$this->ppdLog("isCookieValid: invalid USERID:" . $uid . "!!!");
        }
        return false;
        
    }
    public function isUserLogin($ref="") {
        $oid=session('oid');
        $uid=session('uid');
        $time=session('time');
        $sid=session('sid');
        $ip = $_SERVER['REMOTE_ADDR'];
        $now=time();
        $ori_sid=$oid . $uid . $time . $ip . $oid;
        if (empty($uid)||empty($oid)||empty($time)||$now-$time>60||$sid!=md5($ori_sid)){
            return false;
        }else return $uid;
    }
    protected  function getBalanceFromDb($user_id)
    {
        $m=M('user');
        $user_data=$m->where("UserId='{$user_id}'")->find();
        if($user_data){
            return $user_data['UserBalance'];
        }else{
            if($user_data===false)
                $this->ppdLog("getBalanceFromDb DB ERROR :" . json_encode($m->getDbError()),3);
            else 
                $this->ppdLog("getBalanceFromDb Didn't find User {$user_id}",3);
            return -1;
        }
    }

    protected  function getBalance($access_token)
    {
        /*获取用户资金余额*/
        static $wake_up_time =0;
        static $error_count=0;
        
        //since ppdai api often has unknown error. we let the api sleep for a while to avoid server busying on dealing error.
        if($wake_up_time>time())
                return 0;
        else{
                $wake_up_time=0;
                $error_count=0;
            }
        
        $url = "https://openapi.ppdai.com/balance/balanceService/QueryBalance";
        $request = '{ }';
        $r = $this->send($url, $request,$access_token);
        $balance=-1;
        if(isset($r['Result']))
        {
            if ($r['Result']==0){
                foreach ($r['Balance'] as $arr) {
                    if($arr['AccountCategory']=="用户备付金.用户现金余额"){
                        $balance=$arr['Balance'];
                        break;
                    }
                }
                
                if($balance<0){
                    $str=dump($r,false);
                    $this->ppdLog("ABNORMAL DIDN'T FOUND BALANCE IN :{$str}</br>\n",3);
                }
            }else {
                $this->ppdLog("getBalance ERROR:" . json_encode($r->ResultMessage) . "</br>\n",2);
                $balance=-1;
            }
        }else if(isset($r['Code']) && $r['Code']=="GTW-BRQ-INVALIDTOKEN")
        {
            $this->ppdLog("getBalance ERROR! GTW-BRQ-INVALIDTOKEN </br>\n",2);
            $balance=-400;
        }
        else{
            
            $this->ppdLog("getBalance ERROR! unknown reason:". json_encode($r) ."</br>\n",2);
            $balance=-3;
            if($error_count++>5){
                $wake_up_time=time()+15;
            }
        }
        return $balance;
    }
    public function testToken()
    {    echo "repayment";
        echo $this->getLenderRepayment("2fd988305944f1a0e8a46d7ff4033b51acf99a2fc677d339a847d58969f0bef4c120f93157b266c19ea96e81462a038b7e6a07cba40cd02fff05a4", "53069524");
        echo "balance";
        echo $this->getBalance("2fd988305944f1a0e8a46d7ff4033b51acf99a2fc677d339a847d58969f0bef4c120f93157b266c19ea96e81462a038b7e6a07cba40cd02fff05a4");
    }
    public function getLenderRepayment($accessToken,$listid,$order="")
    {
        #$url = "https://openapi.ppdai.com/invest/RepaymentService/FetchLenderRepayment";
    $url = "https://openapi.ppdai.com/creditor/openapi/fetchLenderRepayment";
        $request = '{"ListingId":'. $listid . ',"OrderId":"' . $order . '"}';
        $result = $this->send($url, $request, $accessToken);
        if(isset($result['Result']))
        {
            if($result['Result']==1)
                return ($result['ListingRepayment']);
            else {
                $this->ppdLog(__FUNCTION__ . "FAILED, ERRMSG:" . $result['ResultMessage'],2);
                return false;
            }
        }
        $this->ppdLog(__FUNCTION__ . "ERROR ERRMSG:" . json_encode($result),2);
        return false;
    }

    protected function getAssessment($access_token){
        $url     = "https://openapi.ppdai.com/listing/openapi/queryUserRiskType";
        $request = '{ }';
        return $this->send($url, $request,$access_token);
    }

    protected function getVolumeList($page, $date){
      $url = 'https://openapi.ppdai.com/charge/volume/list';
      $request = '{ "page": '. $page. ', "date": "'. $date.'"}';
      return $this->send($url, $request);
    }

    public function myCallBack($data)
    {
        if($data)
            $this->ppdLog("successed!");
        else
            $this->ppdLog("failed!");
    }

    /**
     * 刷新AccessToken
     *
     * @param $openid: 用户唯一标识
     * @param $openid: 应用ID
     * @param $refreshtoken: 刷新令牌Token
     */
    function refreshToken($openid, $refreshtoken, $appid = null) {
        if($appid == null)
            $appid = $this->appid;
        $request = '{"AppID":"' . $appid . '","OpenID":"' . $openid. '","RefreshToken":"' . $refreshtoken. '"}';
        $url = "https://ac.ppdai.com/oauth2/refreshtoken";
        return $this->SendAuthRequest ( $url, $request );
    }
    
    //level 0: INFO 1 WARNING 2 ERR  3 ABNORMAL ERR  4: FATAL ERROR
    public function ppdLog($msg,$level=0) {
        
        if($level>=0 && $level<=4){// control output log level.
            $arr=array(" INFO: "," WARNING: "," ERROR: ", " ABNORMAL ERROR: ", " FATAL ERROR ");
            $date=date("Y-m-d",time());
            $midname=date("YmdH");
            if (!is_dir('jdlog/' . $date . '/')) mkdir('jdlog/' . $date. '/');
            $filename="jdlog/$date/" . $midname . ".txt";
            if(!file_exists($filename))
                $bom = chr(0xEF).chr(0xBB).chr(0xBF);
            else
                $bom = "";
            $myfile = fopen($filename, "a+");
            if($myfile){
                $timestamp = date('Y-m-d H:i:s');
                fwrite($myfile, $bom . $timestamp . $arr[$level] .  $msg . "</br>\r\n");
                fclose($myfile);
            }else{
                echo "open $filename err</br>";
            }
        }
    }
    
    
    //this is a ex lock. remember to release it 
    protected function lock($name)
    {
        $fp = fopen("lock/".$name, "a+");
        if(!$fp){
            $this->ppdLog("lock/.$name open failed");
            return 0;
        }
        
        if (flock($fp, LOCK_EX|LOCK_NB)) {  // 进行排它型锁定
            return $fp;
        } else {
            fclose($fp);
            return 0;
        }
        
    }
    protected function unlock($fp){
        flock($fp, LOCK_UN);    // 释放锁定
        fclose($fp);
    }
    protected function cacheSave($filename,$data)
    {
        while(($lock=$this->lock($filename))==0)
            ;//justwait
        {
            $myfile = fopen($filename, "w+");
            $ret=false;
            if($myfile){
                fwrite($myfile, json_encode($data));
                $ret=true;
                fclose($myfile);
            }
            $this->unlock($lock);
        }
    }
    
    protected function cacheGet($filename)
    {
        $ret=false;
        while(($lock=$this->lock($filename))==0)
            ;//justwait
        {
            $myfile = fopen($filename, "a+");
            
            if($myfile){
                $str=fread($myfile, filesize($filename));
                $ret=json_decode($str,true);
                fclose($myfile);
            }
            $this->unlock($lock);
        }
        return $ret;
    
    }
    
    
    protected function cacheSaveLoan($filename,$loan,$expire=60)
    {
        $ret=false;
        if(empty($loan))
            return null;
        $timeout=100000;
        while(($lock=$this->lock($filename))==0 && $timeout>0)
            $timeout--;//justwait
        if($timeout>0){
                $str=file_get_contents($filename);
                $timestamp=time()+$expire;
                $now=time();
                //echo "str:$str</br>";
                $json=json_decode($str,true);
                if(is_array($json)){
                    foreach ($loan as $l)
                    {   
                            $find=false;
                            foreach ($json as $id=>$j)
                            {
                                if(isset($j['timestamp'])&&$j['timestamp']<$now)
                                    unset($json[$id]);
                                else if(isset($l['ListingId'])
                                        &&isset($j['ListingId'])
                                        && $l['ListingId']==$j['ListingId'])
                                {
                                    $find=true;
                                    break;
                                }
                            }
                            if(!$find){
                                $l['timestamp']=$timestamp;
                                array_push($json, $l);
                            }
                    }
                    file_put_contents($filename, json_encode($json));
                }else{
                    foreach ($loan as $id=>$l)
                    {
                        $loan[$id]['timestamp']=$timestamp;
                    }
                    file_put_contents($filename, json_encode($loan));
                }
                $ret=true;
        }else{
            $this->ppdLog("file lock time out");
            $ret=false;
        }
        if($lock)
            $this->unlock($lock);
        return $ret;

    }
    
    protected function cacheGetLoan($filename)
    {
        $ret=false;
        $timeout=100000;
        while(($lock=$this->lock($filename))==0 && $timeout>0)
            $timeout--;//justwait
        $current_time=time();
        if($timeout>0){
                $data=file_get_contents($filename);
                //echo "data:$data</br>";
                $ret= json_decode($data,true);
                if(is_array($ret)){
                    foreach ($ret as $id=>$loan)
                    {
                        if($ret[$id]['timestamp']<$current_time)
                            unset($ret[$id]);
                    }
                }
        }else{
            echo "file lock timeout </br>";
        }
        if($lock)
            $this->unlock($lock);
        return $ret;
    
    }
    /**
     * 获取授权
     *
     * @param $appid: 应用ID
     * @param $code
     */
    function getAuthorize($code, $appid = null) {
        if ($appid == null)
            $appid = $this->appid; 
        $request = '{"AppID": "'. $appid .'","Code": "'. $code .'"}';
        $url = "https://ac.ppdai.com/oauth2/authorize";
        return $this->SendAuthRequest ( $url, $request );
    }
    
    function SendAuthRequest($url, $request) {
        $curl = curl_init ( $url );
        $header = array ();
        $header [] = 'Content-Type:application/json;charset=UTF-8';
    
        curl_setopt ( $curl, CURLOPT_HTTPHEADER, $header );
        curl_setopt ( $curl, CURLOPT_POST, 1 );
        curl_setopt ( $curl, CURLOPT_POSTFIELDS, $request );
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        //curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        $result = curl_exec ( $curl );
        curl_close ( $curl );
    
            $auth = json_decode ( $result, true );
            if ($auth == NULL || $auth == false) {
                return $result;
            }
            return $auth;
    
        //return $result;
    }
    
    /**
     * 向拍拍贷网关发送请求
     * Url 请求地址
     * Data 请求报文
     * AppId 应用编号
     * Sign 签名信息
     * AccessToken 访问令牌
     *
     * @param unknown $url
     * @param unknown $data
     * @param string $accesstoken
     */
    function send($url, $data, $accesstoken = '', $appid = null, $privateKey = null) {
        if($appid == null)
            $appid = $this->appid;
        if($privateKey == null)
            $privateKey == $this->appPrivateKey_p2phunter;
        return $this->SendRequest ( $url, $data, $appid, $accesstoken, $privateKey);
    }
    
    function multiAccountSend($url, $data) {
        static $i=0;
        if($i==0)
            $cur_appid=$this->appid_p2phunter;
        else if($i==1)
            $cur_appid=$this->appid_paizhitou;
        else
            $cur_appid=$this->appid_stockplayer;
        $i=($i+1)%3;
        return $this->SendRequest ( $url, $data, $cur_appid, null);
    }

    // 包装好的发送请求函数
    function SendRequest ( $url, $request, $appId, $accessToken, $key = null){
        $curl = curl_init ( $url );
        if($key==null)
            $key=$this->appPrivateKey_p2phunter;

        $timestamp = gmdate ( "Y-m-d H:i:s", time ()); // UTC format
        $timestap_sign = $this->sign($appId. $timestamp,$key);
    
        $requestSignStr = $this->sortToSign($request);
        $request_sign = $this->sign($requestSignStr,$key);
    
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
    
    
    


    /**
     * 排序Request至待签名字符串
     *
     * @param $request: json格式Request
     */
    function sortToSign($request){
        $obj = json_decode($request);
        $arr = array();
        foreach ($obj as $key=>$value){
            if(is_array($value)){
                continue;
            }else{
                $arr[$key] = $value;
            }
        }
        ksort($arr);
        $str = "";
        foreach ($arr as $key => $value){
            $str = $str.$key.$value;
        }
        $str = strtolower($str);
        return $str;
    }
    
    
    /**
     * RSA私钥签名
     *
     * @param $signdata: 待签名字符串
     */
    function sign($signdata,$key=''){
        if($key==''||$key==null)
            $key=$this->appPrivateKey;
        if(openssl_sign($signdata,$sign,$key))
            $sign = base64_encode($sign);
        return $sign;
    }
    
    
    /**
     * RSA公钥验签
     *
     * @param $signdata: 待签名字符串
     * @param $signeddata: 已签名字符串
     */
    function verify($signdata,$signeddata){
        $signeddata = base64_decode($signeddata);
        if (openssl_verify($signdata, $signeddata, $this->appPublicKey))
            return true;
        else
            return false;
    }
    
    
    /**
     * RSA公钥加密
     *
     * @param $encryptdata: 待加密字符串
     */
    function encrypt($encryptdata){
        openssl_public_encrypt($encryptdata,$encrypted,$this->appPublicKey);
        return base64_encode($encrypted);
    }
    
    
    /**
     * RSA私钥解密
     *
     * @param $decryptdata: 待解密字符串
     */
    function decrypt($encrypteddata, $privateKey = null){
        if($privateKey == null)
            $privateKey = $this->appPrivateKey;
        openssl_private_decrypt(base64_decode($encrypteddata), $decrypted, $privateKey);
        return $decrypted;
    }
    
    
    public  function msectime() {
        list($msec, $sec) = explode(' ', microtime());
        $msectime =  (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;
    }
    public function getTime($server){
        $data  = "HEAD / HTTP/1.1\r\n";
        $data .= "Host: $server\r\n";
        $data .= "Connection: Close\r\n\r\n";
        $fp = fsockopen($server, 80);
        fputs($fp, $data);
        $resp = '';
        while ($fp && !feof($fp))
            $resp .= fread($fp, 1024);
        preg_match('/^Date: (.*)$/mi',$resp,$matches);
        return isset($matches[1])?strtotime($matches[1]):0;
    }
    
    public function getTimeDiff($server)
    {
        $lastsec=$this->getTime($server);
        if($lastsec){
            $cur_msec=$this->msectime();
            while($this->getTime($server)==$lastsec)
                $cur_msec=$this->msectime();
            $timediff=($lastsec+1)*1000-$cur_msec;
            unset($lastsec);
            unset($cur_msec);
        }else{
            $timediff=0;
        }
        return $timediff;
    }
    public function syncTime($timediff)
    {
        $mtime=$this->msectime();
        $stime=($mtime+$timediff)/1000;
        return $stime;
    }
    public function mSyncTime($timediff)
    {
        $mtime=$this->msectime();
        $stime=($mtime+$timediff);
        return $stime;
    }
    public function strToPassedDays($str){
        $now=time();
        $strtime=strtotime($str);
        return ceil(($now-$strtime)/(24*3600));    
    }
    public function strToPassedMonths($str){
        $now=time();
        $strtime=strtotime($str);
        return floor(($now-$strtime)/(24*3600*30));
    }

    public function setAccessType(){
    /* 0: 初始状态. 1: 检测为PC/移动端浏览器访问. 2: 检测为微信访问 */
        if (session('accessType') == 0){
            if (!$this->isWxBrowser()){
                session('accessType', 1);
            } else {
                session('accessType', 2);
            }
        }
    }

    public function displayPage($mobile, $pcPg, $mPg){
        if (session('accessType') == 2){
            /* 微信访问直接跳转到移动页面 */
            $this->assign("mbl", 1);
            $this->display($mPg);
        } else {
            if ($mobile == 0){
                $this->assign("mbl", 0);
                $this->display($pcPg);
            } else {
                $this->assign("mbl", 1);
                $this->assign("applied", $applied);
                $this->display($mPg);
            }
        }
    }

    protected  function couponSN($type,$score){
        $num1=rand(100,499);
        $num2=rand(10,39);
        $num3=rand(1,9);
        $year=date("y",time());
        $month=date("m",time());
        $day=date("d",time());
        $verycode = $num1+$num2*$num3+($score/1000);
        return "$type$num1$year$num2$month$num3$day$verycode";
    }

    protected function isWxBrowser(){
        if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')){
            return true;
        }
        return false;
    }

    protected function stashSet($key, $data){
        $driver = new Stash\Driver\FileSystem(array());
        $pool = new Pool($driver);
        /* key 应该为一个字符串类型，比如 $key='realtime' */
        $item = $pool->getItem($key);

        $pool->save($item->set($data));
        return;
    }

    protected function stashGet($key){
        $driver = new Stash\Driver\FileSystem();
        $pool = new Pool($driver);
        /* key 应该为一个字符串类型，比如 $key='realtime' */
        $item = $pool->getItem($key);

        return $item->get();
    }

}

