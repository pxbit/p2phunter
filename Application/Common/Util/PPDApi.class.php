<?php
namespace Common\Util;

/* 用于 批量生成 curl_multi 请求 */
class CurlRequest {
    public $url         = '';
    public $method      = 'GET';
    public $post_data   = null;
    public $headers     = null;
    public $options     = null;
    public $user_id     = null;
    public $list_id = null;
    public $amount  = null;
    public $order_id  = null;
    public $accesstoken = null;
    public $strategy_id = null;
    /**
     *
     * @param string $url
     * @param string $method
     * @param string $post_data
     * @param string $headers
     * @param array $options
     * @return void
     */
    public function __construct($url, $method = 'GET', $post_data = null, $headers = null, $options = null) {
        $this->url = $url;
        $this->method = strtoupper( $method );
        $this->post_data = $post_data;
        $this->headers = $headers;
        $this->options = $options;
    }
    /**
     * @return void
     */
    public function __destruct() {
        unset ( $this->url, $this->method, $this->post_data, $this->headers, $this->options ,$this->user_id, $this->list_id);
    }
}

/* 拍拍贷API 接口类*/
class PPDApi {

    private $appid;
    private $privateKey;
    private $publicKey;
    
    /*构造函数， 采用APPID 私钥 和 公钥初始化。*/
    function __construct($appid, $privateKey, $publicKey = null)
    {
        $this->appid = $appid;
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
    }
    
    /**
     * 获取授权
     *
     * @param $appid: 应用ID
     * @param $code
     */
    function getAuthorize($code) {
        $request = '{"AppID": "'. $this->appid .'","Code": "'. $code .'"}';
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

    public  function getBalance($access_token)
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
    
    /**
     * 刷新AccessToken
     *
     * @param $openid: 用户唯一标识
     * @param $openid: 应用ID
     * @param $refreshtoken: 刷新令牌Token
     */
    function refreshToken($openid, $refreshtoken) {
        $request = '{"AppID":"' . $this->appid . '","OpenID":"' . $openid. '","RefreshToken":"' . $refreshtoken. '"}';
        $url = "https://ac.ppdai.com/oauth2/refreshtoken";
        return $this->SendAuthRequest ( $url, $request );
    }
    
    public function getAssessment($access_token){
        $url     = "https://openapi.ppdai.com/listing/openapi/queryUserRiskType";
        $request = '{ }';
        return $this->send($url, $request,$access_token);
    }
    
    public function getVolumeList($page, $date){
        $url = 'https://openapi.ppdai.com/charge/volume/list';
        $request = '{ "page": '. $page. ', "date": "'. $date.'"}';
        return $this->send($url, $request);
    }
    
    public function getLenderRepayment($accessToken,$listid,$order="")
    {
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
    
    public function getLoanList(){
        //获取所有借款列表，API 每页200条，获取到 每页不是200条表明是最后一页，将所有列表合成为一个，返回列表，如果失败，返回空表。
        $page_index = 1;
        $loan_list_array=array();
        #$this->time_diff = $this->getTimeDiff($this->sync_server);
        $last_time = $this->msectime()-5000;
        do {
            $list=array();
            $url = "https://openapi.ppdai.com/listing/openapiNoAuth/loanList";
            $startDateTime = date("Y-m-d H:i:s", $last_time/1000) .  "." . ($last_time - floor($last_time/1000)*1000);
            $request = '{ "PageIndex": ' . $page_index . ',    "StartDateTime": "'. $startDateTime . '" }';
            $result = $this->send($url, $request);
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
    
    public function getLoanDetail($loan_id_list){
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
    
    
    public function send($url, $request, $accesstoken = '') {
        return $this->SendRequest ( $url, $request, $this->appid, $this->privateKey, $accesstoken );
    }
    
    public function SendRequest ( $url, $request, $appId, $privateKey, $accessToken){
        $curl = curl_init ( $url );
        $timestamp = gmdate ( "Y-m-d H:i:s", time ()); // UTC format
        $timestap_sign = $this->sign($appId. $timestamp, $privateKey);
    
        $requestSignStr = $this->sortToSign($request);
        $request_sign = $this->sign($requestSignStr, $privateKey);
    
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
    
    /* 余额查询  */
    public  function makeGetBalanceRequest($user_id, $accesstoken)
    {
        $url = "https://openapi.ppdai.com/balance/balanceService/QueryBalance";
        $request = '{ }';
        $r = $this->send($url, $request, $accesstoken);
        $req = $this->makeRequest($url, $request,$accesstoken);
        $req->user_id = $user_id;
        $req->accesstoken = $accesstoken;
        return $req;
        
    }
    
    /* 投标API 添加投标用户和策略信息，方便回调处理。*/
    public function makeBidRequest($user_id,$strategy_id,$list_id,$amount,$accesstoken)
    {
        $url = "https://openapi.ppdai.com/listing/openapi/bid";
        $request = '{ "ListingId": '. $list_id . ',"Amount": ' . $amount . ',"UseCoupon":"true" }';
        $req = $this->makeRequest($url, $request,$accesstoken);
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
        $req = $this->makeRequest($url, $request,$accesstoken);
        $req->user_id = $user_id;
        $req->list_id = $list_id;
        $req->amount = $amount;
        $req->order_id = $order_id;
        $req->accesstoken = $accesstoken;
        $req->strategy_id = $strategy_id;
        return $req;
    
    }
    
    public function makeRequest($url, $request, $accesstoken = '')
    {
        $timestamp = gmdate ( "Y-m-d H:i:s", $this->msectime()); // UTC format
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
        $req = new CurlRequest ($url, 'POST',$request, $header, $option);
        return $req;
    }
    
    /**
     * 排序Request至待签名字符串
     *
     * @param $request: json格式Request
     */
    public function sortToSign($request){
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
    public function sign($signdata,$key=''){
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
    public function verify($signdata,$signeddata){
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
    public function encrypt($encryptdata){
        openssl_public_encrypt($encryptdata,$encrypted,$this->appPublicKey);
        return base64_encode($encrypted);
    }
    
    
    /**
     * RSA私钥解密
     *
     * @param $decryptdata: 待解密字符串
     */
    public function decrypt($encrypteddata){
        openssl_private_decrypt(base64_decode($encrypteddata),$decrypted,$this->appPrivateKey);
        return $decrypted;
    }
    
    private  function msectime() {
        list($msec, $sec) = explode(' ', microtime());
        $msectime =  (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;
    }
    
    //level 0: INFO 1 WARNING 2 ERR  3 ABNORMAL ERR  4: FATAL ERROR
    private function ppdLog($msg,$level=0) {
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
}