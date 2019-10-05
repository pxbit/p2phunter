<?php
namespace Home\Controller;
use Think\Controller;
const COUPON_UNIT                    = 10000;
const PROMOTION_TYPE				 = "推荐";//促销推荐二者智能择其一。
const JDCODE_COUPON_RATIO			 = 0.1;
class WxpayController extends BaseController 
{
    private $home_url;
    private $return_url;
    private $notify_url;
    private $wxconfig = null;
    public function _initialize()
    {
        vendor('wxsdk.lib.WxPay#Data');
        vendor('wxsdk.pay.WxPay#NativePay');
        vendor('wxsdk.pay.phpqrcode.phpqrcode');
        vendor('wxsdk.pay.log');
        $today = date("Y-m-d", time());
        $handler = new \CLogFileHandler("./jdlog/wxpay_" . $today . ".log");
        \Log::Init($handler);
        
        if($_SERVER['SERVER_NAME']=="localhost")
            $home="/p2phunter/home";
        else
            $home="/home";
        $this->home_url=('http://') . $_SERVER['SERVER_NAME'] .
            ':' . $_SERVER['SERVER_PORT']. $home;
        $this->return_url=$this->home_url . "/wxpay/recheck";
        $this->notify_url=$this->home_url . "/wxpay/notify";

        $this->prtCfg = C("PROMOTE_CFG");
    }
    
    
    
    public function qrcode()
    {
        $url = urldecode($_GET["data"]);
        if(substr($url, 0, 6) == "weixin"){
        	\QRcode::png($url);
        }else{
        	header('HTTP/1.1 404 Not Found');
        }
    }
    public function test()
    {
        echo "sucess";
    }
    public function index($prc, $ptype)
    {
        /**
         * 流程：
         * 1、调用统一下单，取得code_url，生成二维码
         * 2、用户扫描二维码，进行支付
         * 3、支付完成之后，微信服务器会通知支付成功
         * 4、在支付成功通知中需要查单确认是否真正支付成功（见：notify.php）
         */
        $param=$this->getParam($prc,$ptype);
        if($this->checkParamValid($param)){
            $uid  = $param['uid']; 
            $notify = new \NativePay();
            $input = new \WxPayUnifiedOrder();
            $input->SetBody("投标服务费");
            $input->SetAttach("WEB");
            $random = rand(10, 99);
            $checksum = ($random + $uid + 55)%100;
            if($checksum < 10)
                $checksum = "0" . $checksum;
            $tradeNo = $random.date("YmdHis").($uid+100000).$checksum;
            $input->SetOut_trade_no($tradeNo);
            $input->SetTotal_fee($param['price']*100);
            $input->SetTime_start(date("YmdHis"));
            $input->SetTime_expire(date("YmdHis", time() + 600));
            $input->SetGoods_tag("test");
            $input->SetNotify_url($this->notify_url);
            $input->SetTrade_type("NATIVE");
            $input->SetProduct_id($prc);
            
            $result = $notify->GetPayUrl($input);
            $url2 = $result["code_url"];
            $this->assign("typeName", "微信");
            $this->assign("price", $param['price']);
            $this->assign("type", $param['type']);
            $this->assign("qrurl", $url2);
            $user_data['tradeNo'] = $tradeNo;
            $user_data['timeout'] = 600;
            $user_data['money'] = $param['price'];
            $user_data['pay_id'] = $uid;
            $user_data['pay_time'] = time();
            $this->assign("user_data", json_encode($user_data));
            $this->display("default/wxpay");
            \Log::INFO("create order ". json_encode($user_data));
        }else{
            die("wxpay invalid access!");
        }
    }

    public function qry($tid)
    {
        $status = 'NOTLOGIN';
        if($this->isCookieValid()){
            $status = 'UID INVALID';
            $tradeUid = $this->checkTradeNo($tid);
            $uid = cookie('uid');
            if($tradeUid && $tradeUid == $uid){
                $status = 'NONE1';
                $m = M("pay_order");
                $result = $m->where("UserId = '{$uid}' and pay_tag = '{$tid}'")->find();
                if($result === false){
                    \Log::ERROR("wxpay qry db error");
                    $status = 'DB_ERROR';
                }else if($result){
                    $status = 'SUCCESS';
                }
            }
        }
        echo $status;
    }
    
    public function recheck()
    {
        /*0：成功 -1:失败 -2:参数有误 */
        \Log::INFO("wxPayController: received  Order msg, data: " . json_encode($_GET));
        if($this->isCookieValid()){
            $uid = cookie('uid');
            $tradeNo = $_GET['pay_no'];
            $tradeUid = $this->checkTradeNo($tradeNo);
            if($tradeUid && $tradeUid == $uid){
                $m = M("pay_order");
                $result = $m->where("pay_tag = '{$tradeNo}'")->find();
                if ($result) {
                    $result = '充值成功';
                    \Log::INFO("wxPayController/recheck: user " . $uid. " " . $result);
                    $this->assign("paystatus", $result);
                    $this->assign("paytips", "如未到账请联系我们！");
                    $this->display("default/payresult");
                } else {
                    $result = "充值失败";
                    \Log::INFO("wxPayController/recheck: user " . $uid . " " . $result);
                    $this->assign("paystatus", $result);
                    $this->assign("paytips", "如果已产生扣费，请截屏后联系我们！");
                    $this->display("default/payresult");
                }
            }
        }
    }

    private function getParam($prc,$ptype){
        if($this->isCookieValid()){
            $param['type']=$ptype;
            $param['price']=$prc;
            $param['uid']=cookie('uid');
            $param['outTime']=360;//二维码超时时间 6分钟。
            $param['price']=floor($param['price']/3700);
            return $param;
        }else{
            return null;
        }
    }

    private function checkParamValid($param){
        /*check param*/
        if(isset($param['price']) && ($param['price'] == 5||$param['price'] == 20||
            $param['price'] == 50||$param['price'] == 100||$param['price'] == 200||
            $param['price'] == 500 || $param['price'] == 1000)){
            if(isset($param['type']) && $param['type'] == 4)
                return true;
        }
        return false;
    }
    
    public function notify()
    {
    	$config = new \WxPayConfig();
    	$this->Handle($config, false);
    
    }
    
    final private function Handle($config, $needSign = true)
    {
    	$this->wxconfig = $config;
    	$msg = "OK";
    	$reply = new \WxPayNotifyReply();
    	//当返回false的时候，表示notify中调用NotifyCallBack回调失败获取签名校验失败，此时直接回复失败
    	$result = \WxpayApi::notify($config, array($this, 'NotifyCallBack'), $msg);
    	if($result == false){
    		$reply->SetReturn_code("FAIL");
    		$reply->SetReturn_msg($msg);
    		$this->ReplyNotify(false, $reply);
    		\Log::DEBUG("Wxpay Handle 调用NotifyCallBack回调失败获取签名校验失败");
    		return;
    	} else {
    		//该分支在成功回调到NotifyCallBack方法，处理完成之后流程
    		$reply->SetReturn_code("SUCCESS");
    		$reply->SetReturn_msg("OK");
    	}
    	$this->ReplyNotify($needSign, $reply);
    }
    
    public function NotifyCallBack($data)
    {
        $msg = "OK";
    	\Log::INFO("NotifyCallBack" . json_encode($data));
        $result = $this->NotifyProcess($data, $this->wxconfig, $msg);
        return $result;
    }
    
    /**
     *
     * 回复通知
     * @param bool $needSign 是否需要签名输出
     */
    final private function ReplyNotify($needSign = true, $reply)
    {
    	//如果需要签名
    	if($needSign == true &&
    			$reply->GetReturn_code() == "SUCCESS")
    	{
    		$reply->SetSign($this->wxconfig);
    	}
    
    	$xml = $reply->ToXml();
        \Log::DEBUG("wxpay ReplyNotify!" . $xml);
    	\WxpayApi::replyNotify($xml);
    }
    
    //重写回调处理函数
    /**
     * @param WxPayNotifyResults $data 回调解释出的参数
     * @param WxPayConfigInterface $config
     * @param string $msg 如果回调处理失败，可以将错误信息输出到该方法
     * @return true回调出来完成不需要继续回调，false回调处理未完成需要继续回调
     */
    public function NotifyProcess($objData, $config, &$msg)
    {
    	$data = $objData->GetValues();
    	//TODO 1、进行参数校验
    	if(!array_key_exists("return_code", $data)
    			||(array_key_exists("return_code", $data) && $data['return_code'] != "SUCCESS")) {
    		$msg = "异常异常";
    		\Log::ERROR("支付通知异常!");
    		return false;
    	}
    	if(!array_key_exists("transaction_id", $data)){
    		$msg = "输入参数不正确";
    		return false;
    	}
    
    	//TODO 2、进行签名验证
    	try {
    		$checkResult = $objData->CheckSign($config);
    		if($checkResult == false){
    			//签名错误
    			\Log::ERROR("签名错误...");
    			return false;
    		}
    	} catch(\Exception $e) {
    		\Log::ERROR(json_encode($e));
    	}
    
    	$notfiyOutput = array();
    
    
    	//查询订单，判断订单真实性
    	if(!$this->Queryorder($data["transaction_id"])){
    		$msg = "订单查询失败";
    		return false;
    	}
    	
    	if($this->savePayToDb($data) == false){
    	    $msg = "订单保存至数据库失败";
    	    return false;
    	}
    	
    	return true;
    }
    
    //查询订单
    private function Queryorder($transaction_id)
    {
    	$input = new \WxPayOrderQuery();
    	$input->SetTransaction_id($transaction_id);
    
    	$config = new \WxPayConfig();
    	$result = \WxpayApi::orderQuery($config, $input);
    	if(array_key_exists("return_code", $result)
    			&& array_key_exists("result_code", $result)
    			&& $result["return_code"] == "SUCCESS"
    			&& $result["result_code"] == "SUCCESS")
    	{
    		return true;
    	}
    	return false;
    }
    
    public function q($tid)
    {
        if(!$this->isCookieValid() || cookie('uid') != 1)
            return;
    	$input = new \WxPayOrderQuery();
    	$input->SetOut_trade_no($tid);
    
    	$config = new \WxPayConfig();
    	$result = \WxpayApi::orderQuery($config, $input);
    	if(array_key_exists("return_code", $result)
    			&& array_key_exists("result_code", $result)
    			&& $result["return_code"] == "SUCCESS"
    			&& $result["result_code"] == "SUCCESS")
    	{
            var_dump($result);
    		return true;
    	}
        echo "not valid";
        return false;
   }

  
    /*
     * NotifyProcess
     * {"appid":"wx480313d2a257d563","attach":"WEB","bank_type":"CFT","cash_fee":"1",
     * "fee_type":"CNY","is_subscribe":"Y","mch_id":"1511173521","nonce_str":"ywep4niyz4aekwfi6a7df122qyntu6tw",
     * "openid":"oASN502Qpn9HWzbs8z6gihHouFIs","out_trade_no":"18551120181021001856","result_code":"SUCCESS",
     * "return_code":"SUCCESS","sign":"E67EA5C3AE64EE2A514C692D26FE5B2444AFCAAC787041003C568817AFA67B15",
     * "time_end":"20181021002118","total_fee":"1","trade_type":"NATIVE","transaction_id":"4200000161201810216757488542"
     * }
     */
    public function testSavePayToDb()
    {
        $uid = 1;
        $random = rand(10,100);
        $checksum = ($random + $uid)%100;
        $trade_no = $random.date("YmdHis").($uid+100000).$checksum;
        $data = '{"appid":"wx480313d2a257d563","attach":"WEB","bank_type":"CFT","cash_fee":"1",
        "fee_type":"CNY","is_subscribe":"Y","mch_id":"1511173521","nonce_str":"ywep4niyz4aekwfi6a7df122qyntu6tw",
        "openid":"oASN502Qpn9HWzbs8z6gihHouFIs","out_trade_no":"962018102422071610000152","result_code":"SUCCESS",
        "return_code":"SUCCESS","sign":"E67EA5C3AE64EE2A514C692D26FE5B2444AFCAAC787041003C568817AFA67B15",
        "time_end":"20181021002118","total_fee":"1","trade_type":"NATIVE","transaction_id":"4200000161201810216757488542"
        }';
        $this->savePayToDb(json_decode($data, true));
    }
    
    private function checkTradeNo($tradeNo)
    {
        $checksum = substr($tradeNo, -2);
        $uid = (int)(substr($tradeNo, -8, 6)) - 100000;
        $random = substr($tradeNo, 0, 2);
        if (($uid + $random + 55)%100 != $checksum){
        	\Log::DEBUG("tradeNo check uid fail:" . $tradeNo);
        	return false;
        }else{
            return $uid;
        }
        
    }
    
    private function savePayToDb($data)
    { 
        //校验用户ID
        $uid = $this->checkTradeNo($data['out_trade_no']);
        if(false == $uid)
            return false;

        $db_data['UserId']     = $uid; //需要充值的ID 或订单号 或用户名
        $db_data['money']      = ((float)$data['cash_fee'])/100; //实际付款金额
        $db_data['price']      = ((float)$data['total_fee'])/100 ; //订单的原价
        $db_data['type']       = 4; //支付方式
        $db_data['pay_no']     = $data['transaction_id']; //支付流水号
        $db_data['param']      = $data['openid']; //自定义参数 原封返回 您创建订单提交的自定义参数
        $db_data['pay_time']   = $data['time_end']; //付款时间戳
        $db_data['pay_tag']    = $data['out_trade_no']; //支付备注 仅支付宝才有 其他支付方式全为0或空
        $db_data['status']     =  $data['status'];
        $db_data['creat_time'] = time(); //创建数据的时间戳
        
        if ($db_data['money'] <= 0 || $db_data['money'] >1001 || empty($db_data['UserId']) ||
        		$db_data['pay_time'] <= 0 || empty($db_data['pay_no'])) {
        	\Log::DEBUG("INVALID PARAM:" . json_encode($db_data));
        	return false; //测试数据中 唯一标识必须包含这些
        }
        
        $user   = M("user");
        $order  = M("pay_order");
        $result = $order->where("pay_no = '" . $db_data['pay_no'] . "'")->find();
        if(!$result){
        	$db_data['status'] = CODEPAY_SUCCESS;
        	$result=$order->add($db_data);
        	if($result===false){
        		\Log::DEBUG("savePayToDb ADD DB ERROR :" . json_encode($db_data));
        		return false ;
        	}else{
        		$score= $this->adjustScoreByCoupon($db_data['UserId'],$db_data['pay_no'], $db_data['money']);
        		$result=$user->where("UserId='" . $db_data['UserId'] ."'" )->setInc("Score",$score);
        		if($result===false){
        			\Log::ERROR("savePayToDb DB SET INC ERROR :" . json_encode($db_data));
        			return false;
        		}else if($result){
        		    \Log::INFO("savePayToDb" . json_encode($db_data));
        		}
        	}
        }else{/* 订单已存在，检查Coupon是否添加成功  */
            \Log::DEBUG("savePayToDb PAY NO EXIST :" . json_encode($db_data));
        }
        return true;
    }

    public function getDsctPricecLevel($money) {
        if($money < 100) {
            $price = 50;
        } else if ($money < 200) {
            $price = 100;
        } else if ($money < 500) {
            $price = 200;
        } else if ($money < 1000) {
            $price = 500;
        }else {
            $price = 1000;
        }

        return $price;
    }

    private function adjustScoreByCoupon($userid, $payno, $money) {
        $start  = $this->prtCfg['START_DATE'];
        $end    = $this->prtCfg['END_DATE'];
        $tm     = date("Y-m-d H:i:s",time());

        \Log::INFO("wxPayController: user " . $userid . " charged " . $money . " RMB");
        /* 在活动时间之外，不给与优惠 */
        if((strtotime($tm) < strtotime($start)) || 
           (strtotime($tm) > strtotime($end))){
           \Log::INFO("PayController: user " . $userid . "charge " . $money . " RMB, 
               didnot get coupon because of time overdue!");
           $score = $money  * COUPON_UNIT;
           return $score;
        }
		if("促销"== PROMOTION_TYPE){
	        $price = $this->getDsctPricecLevel($money);
	        /* 获取对应的折扣配置信息 */
	        $discArr = C("DISCOUNT_CFG");
	        $disc    = 0;
	        foreach($discArr as $key => $val) {
	            if ($key == $price) {
	                $disc = $discArr[$key];
	                break;
	            }
	        }
	        $m   = M('user');
	        $res = $m->where('UserId = '.$userid.' AND RegSrc = 1')->find();
	        if($res) {
	            $disc = 2 * $disc;/* 本地注册会员，双倍优惠 */
	        }
		}else{
			$um = M("User");
			$user = $um->where("UserId = '{$userid}'")->find();
			$disc = 0;
			if($user){
				if(null != $user['JdCode'] && $user['CreateTime'] > date("Y-m-d H:i:s",strtotime("-12 month")))
				{
					if($this->firstPay($userid)){
						$disc = $money*JDCODE_COUPON_RATIO*2;
					}
					$this->updateJdCodeCoupon($payno, $user['UserName'], ($money*JDCODE_COUPON_RATIO*COUPON_UNIT), $user['JdCode']);
				}else{
				    \Log::INFO("coupon no add {$userid} no jdcode or user time expired!");
				}
			}
		}

        /* 根据优惠政策，将实际充值钱对应的积分调整上去 */
        $coupon =  $disc * COUPON_UNIT;
        $this->updateCoupon($userid, $payno, $coupon);
        $score   = $money * COUPON_UNIT + $coupon;
        return $score;
    }

    public function firstPay($userid){
    	$om =M("pay_order");
        $records = $om->where("UserId = '{$userid}'")->count();
    	return  $records && $records < 2;
    }

    private function updateJdCodeCoupon($payno, $username, $coupon, $jdcode) {
    	if ($coupon > 0){
    		$userid = hexdec($jdcode)-500000;
    		$new_payno = $payno . "|" . $username . "|" . $jdcode;
    		$data['PayNo']      = $new_payno;
    		$data['TotalQuota'] = $coupon;
    		$data['ObtainDate'] = date("Y-m-d H:i:s",time());
    		$data['ExpireDate'] = $this->prtCfg['END_DATE'];
    		$data['Reason']     = "推荐奖励";
    		$data['UseDate']    = date("Y-m-d H:i:s",time());
    		$data['SN']         = $this->couponSN(3, $coupon);
    		$data['Used']       = 1; /* 立即到账使用 */
    		$data['Type']       = 3; /* 推荐奖励 */
    
    		$cpnTbl = M("coupon");
    		$record = $cpnTbl->where("PayNo = '{$new_payno}'")->find();
    		if(!$record) {
    			$data['UserId'] = $userid;
    			$status = $cpnTbl->add($data);
    			if($status===false){
    				\Log::INFO("wxPayController: user " . $userid . " coupon add ERROR:" .
    						$cpnTbl->getDbError() . " last SQL:". $cpnTbl->getLastSql());
    			}else{
    				$um = M("user");
    				$result=$um->where("UserId='{$userid}'" )->setInc("Score",$coupon);
    				if($result === false){
    					\Log::INFO("jdcode coupon store faild. UserId:$userid");
    				}
    				\Log::INFO("PayController: user " . $userid . " coupon add SUCCESS!");
    			}
    		}else{
    			$status = $cpnTbl->where("PayNo= '{$new_payno}'")->save($data);
    			if($status===false)
    				\Log::ERROR("wxPayController: user " . $userid. "coupon save ERROR:" .
    						$cpnTbl->getDbError() . " last SQL:". $cpnTbl->getLastSql());
    			else
    				\Log::INFO("PayController: user " . $userid. " coupon save SUCESS!");
    		}
    		\Log::INFO("PayController: user " . $userid .  " get coupon " . $data['TotalQuota'] . " DB");
    	}
    }
    

    private function updateCoupon($userid, $payno, $coupon) {
    	if ($coupon > 0){
	        $data['PayNo']      = $payno;
	        $data['TotalQuota'] = $coupon;
	        $data['ObtainDate'] = date("Y-m-d H:i:s",time());
	        $data['ExpireDate'] = $this->prtCfg['END_DATE']; 
	        $data['Reason']     = "充值优惠";
	        $data['UseDate']    = date("Y-m-d H:i:s",time());
	        $data['SN']         = $this->couponSN(1, $coupon);
	        $data['Used']       = 1; /* 立即到账使用 */
	        $data['Type']       = 1; /* 非返券形式 */
	        $data['UserId'] 	= $userid;
	
	        $cpnTbl = M("coupon");
	        $record = $cpnTbl->where("Userid = '{$userid}' and PayNo ='{$payno}'")->find();
	        if(!$record) {
	            $status = $cpnTbl->where("Userid = '{$userid}' and PayNo ='{$payno}'")->add($data);
	            if($status===false)
	                \Log::ERROR("wxPayController: user " . $userid . " coupon add ERROR:" .
	                $cpnTbl->getDbError() . " last SQL:". $cpnTbl->getLastSql());
	            else
	                \Log::INFO("wxPayController: user " . $userid . " coupon add SUCCESS!");
	        }else{
	            $status = $cpnTbl->where("Userid = '{$userid}' and PayNo ='{$payno}'")->save($data);
	            if($status===false)
	                \Log::ERROR("wxPayController: user " . $userid. "coupon save ERROR:" .
	                $cpnTbl->getDbError() . " last SQL:". $cpnTbl->getLastSql());
	            else
	                \Log::INFO("wxPayController: user " . $userid. " coupon save SUCESS!");
	        }
	        \Log::INFO("wxPayController: user " . $userid .  " get coupon " . $data['TotalQuota'] . " DB");
    	}
    }

}
