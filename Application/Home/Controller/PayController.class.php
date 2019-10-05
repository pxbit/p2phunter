<?php
namespace Home\Controller;
use Think\Controller;
use Common\Util\CodepayInterface as CI;
use Common\Util\CodepaySubmit as CodepaySubmit;
use Common\Util\CodepayNotify as CodepayNotify;

const COUPON_UNIT                    = 10000;
const PROMOTION_TYPE				 = "促销";//推荐或者促销，二者只开一个。
/* 0 为未支付,1为支付成功但通知失败,2为全部完成,小于0则是存在错误 */
const CODEPAY_NOTPAY                 = 0;
const CODEPAY_PAYSUCCESS_NOTIFYFAIL  = 1;
const CODEPAY_SUCCESS                = 2;
const JDCODE_COUPON_RATIO			 = 0.1;

class PayController extends BaseController 
{
    private $code_pay_id="12733";
    private $home_url;
    private $return_url;
    private $notify_url;
    private $codepay_config;
    public function _initialize()
    {
        if($_SERVER['SERVER_NAME']=="localhost")
            $home="/p2phunter/home";
        else
            $home="/home";
        $this->codepay_config['key'] = 'your code pay key';
        $this->home_url=(CI::is_HTTPS() ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] .
            ':' . $_SERVER['SERVER_PORT']. $home; //API安装路径 最终为http://域名/codepay
        $this->return_url=$this->home_url . "/pay/recheck";
        $this->notify_url=$this->home_url . "/pay/notify";

        $this->prtCfg = C("PROMOTE_CFG");
    }

    public function index($prc,$ptype){
        $param=$this->getParam($prc,$ptype);
        if($this->checkParamValid($param)){
            $param["call"]  = "callback"; //回调的Javascript函数
            $codepaySubmit = new CodepaySubmit($this->codepay_config);
            $codepay_json_url = CI::getApiHost() . "creat_order/?";
            $codepay_json_url .= $codepaySubmit->buildRequestParaToString($param);

            $typeName = CI::getTypeName($param['type']);
            $subject = '金雕猎手投标服务费'; //商品名 如 会员账号：张三 充值100元 该模式可自行开发
            $user_data = array("subject" => $subject, "return_url" => $param['return_url'],
                "type" => $param['type'], "outTime" => $param['outTime'], "codepay_id" => $param['id']);

            $this->assign("typeName",$typeName);
            $this->assign("price",$param['price']);
            $this->assign("type",$param['type']);
            $this->assign("user_data",json_encode($user_data));
            $this->assign("codepay_json_url",$codepay_json_url);
            $this->display("default/pay");
        }else{
            die("invalid access!");
        }
    }

    public function recheck()
    {
        /*0：成功 -1:失败 -2:参数有误 */
        $this->ppdLog("PayController: received codepay createOrder msg, data: " . json_encode($_GET));
        $codepayNotify = new CodepayNotify($this->codepay_config);
        $verify_result = $codepayNotify->verifyAll(); //这里验证的是全部参数 这样软件端也能调试
        if ($verify_result  && $_GET['pay_no']) {
            $result = '充值成功';
            $this->ppdLog("PayController/recheck: user " . $_GET['pay_id'] . " " . $result);
            $this->assign("paystatus",$result);
            $this->assign("paytips","如未到账请联系我们！");
            $this->display("default/payresult");
        } else {
            $result = "充值失败";
            $error_msg = defined('DEBUG') && DEBUG ? "签名验证失败了" : '';
            $this->ppdLog("PayController/recheck: user " . $_GET['pay_id'] . " " . $result);
            $this->assign("paystatus",$result);
            $this->assign("paytips","如果已产生扣费，请联系我们！");
            $this->display("default/payresult");
        }
    }

    public function notify()
    {
        $this->ppdLog("PayController: received codepay notify msg, data: " . json_encode($_POST));
        $codepayNotify = new CodepayNotify($this->codepay_config);
        $verify_result = $codepayNotify->verifyNotify();
        if ($verify_result && $_POST['pay_no']) { //验证成功
           if(CODEPAY_PAYSUCCESS_NOTIFYFAIL == $_POST['status']){
               $status = "充值成功但通知失败";
            }else if(CODEPAY_SUCCESS == $_POST['status']){
               $status = "充值成功";
            }else{
               $status = "充值成功，但出现其他未知错误";
            }
           $this->ppdLog("PayController/notify: user " . $_POST['pay_id'] .
               " 的codepay server当前状态:{$status}.");

            $result = $this->codePayHandle($_POST); //调用示例业务代码 处理业务获得返回值
            $this->ppdLog("PayController/notify: user " . $_POST['pay_id'] . " 的codePayHandle结果:". $result);
            if ($result == 'ok' || $result == 'success') { //返回的是业务处理完成
                exit($result); //业务处理完成 下面不执行了
            } else {
                echo(defined('DEBUG') && DEBUG ? $result : 'no'); //正式环境 直接打印no 不返回任何错误信息
            }
        } else {
            echo "fail";
        }
    }

    private function getParam($prc,$ptype){
        if($this->isCookieValid()){
            $param['id']=(int)$this->code_pay_id;
            $param['type']=$ptype;
            $param['price']=$prc;
            $param['pay_id']=cookie('uid');
            $param['param']='';
            $param['act']=0;//是否开启认证版的免挂机功能,一般为0，认证版免挂机为1.
            $param['outTime']=360;//二维码超时时间 6分钟。
            $param['page']=3;//自定义付款页面
            $param['return_url']=$this->return_url;
            $param['notify_url']=$this->notify_url;
            $param['style']=1;//支付页面类型，目前无效
            $param['pay_type']=1;//启用支付宝官方接口，目前无效
            $param['qrcode_url']='';//本地QR 路径，暂不使用。
            $param['chart']='utf-8';
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
            if(isset($param['type']) && $param['type']==3)
                return true;
        }
        return false;
    }

    private function codePayHandle($data)
    { //业务处理例子 返回一些字符串
        $db_data['UserId']     = $data['pay_id']; //需要充值的ID 或订单号 或用户名
        $db_data['money']      = (float)$data['money']; //实际付款金额
        $db_data['price']      = (float)$data['price']; //订单的原价
        $db_data['type']       = (int)$data['type']; //支付方式
        $db_data['pay_no']     = $data['pay_no']; //支付流水号
        $db_data['param']      = $data['param']; //自定义参数 原封返回 您创建订单提交的自定义参数
        $db_data['pay_time']   = (int)$data['pay_time']; //付款时间戳
        $db_data['pay_tag']    = $data['tag']; //支付备注 仅支付宝才有 其他支付方式全为0或空
        $db_data['status']     =  $data['status'];
        $db_data['creat_time'] = time(); //创建数据的时间戳

        if ($db_data['money'] <= 0 || $db_data['money'] >1001 || empty($db_data['UserId']) ||
            $db_data['pay_time'] <= 0 || empty($db_data['pay_no'])) {
            $this->ppdLog("INVALID PARAM:" . json_encode($db_data),1);
            return '缺少必要的一些参数'; //测试数据中 唯一标识必须包含这些
        }

        $user   = M("user");
        $order  = M("pay_order");
        $result = $order->where("pay_no='" . $db_data['pay_no'] . "'")->find();
        if(!$result){
            $db_data['status'] = CODEPAY_SUCCESS; 
            $result=$order->add($db_data);
            if($result===false){
                $this->ppdLog("codePayHandle ADD DB ERROR :" . json_encode($db_data),3);
                return "数据库错误，请联系管理员！";
            }else{
                $score= $this->adjustScoreByCoupon($db_data['UserId'],$db_data['pay_no'], $db_data['money']);
                $result=$user->where("UserId='" . $db_data['UserId'] ."'" )->setInc("Score",$score);
                if($result===false){
                    $this->ppdLog("codePayHandle DB SET INC ERROR :" . json_encode($db_data),3);
                    return "数据库错误，请联系管理员！";
                }
            }
        }else{/* 支付成功但通知失败的处理  */
            if(CODEPAY_PAYSUCCESS_NOTIFYFAIL == $db_data['status']) {
                /* 检查pay_order表中与该流水单对应的金额是否一致，如果一致则检查对应的coupon值是否
                 * 正确, 如果二则均正确, 则给codepay返回ok, 通知codepay补单成功. */
                if($result['money'] == $db_data['money'] ) {
                    /* 检查coupon是否成功添加 */
                    /*以下代码，逻辑有问题，coupon金额不对，暂时注销*/
//                     $couponTbl = M('coupon');
//                     $couponRrd = $couponTbl->where("PayNo=".$result['pay_no'])->find();
//                     /* 如果没有该(uid, payno)对应的coupon, 或者有但是数据不一致,认为数据已经写脏,重
//                      * 新更新 */
//                     if(!$couponRrd || $couponRrd['TotalQuota'] != $result['money'] * COUPON_UNIT ){
//                         $coupon = $result['money'] * COUPON_UNIT;
//                         $this->updateCoupon($db_data['UserId'], $db_data['pay_no'], $coupon);
//                         $this->ppdLog("PayController/notify: user " . $db_data['UserId'] . " 补单成功.");
//                         return "ok"; 
//                     } else {
//                         /* coupon数据正确, 用户已付款成功, 通知补单成功*/
//                         $this->ppdLog("PayController/notify: user " . $db_data['UserId'] . " 补单成功.");
                        return "ok";
//                     }
                }else{
                    return "codepay server端的补单参数和原始单据的money参数不一致," .
                        "原始的为" . $result['money'] . ",补单的为" . $db_data['money'];
                }
            }
        }
        return "ok";
    }

    /* 计算折扣区间, 比如充值100以上但小于200, 按照充值100的折扣算;小于100按照50算.*/
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
        $tm     = date("Y-m-d h:i:s",time());

        $this->ppdLog("PayController: user " . $userid . " charged " . $money . " RMB", 0);
        /* 在活动时间之外，不给与优惠 */
        if((strtotime($tm) < strtotime($start)) ||
           (strtotime($tm) > strtotime($end))){
           $this->ppdLog("PayController: user " . $userid . "charge " . $money . " RMB,
               didnot get coupon because of time overdue!", 0);
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
				if(null != $user['JdCode'] && $user['CreateTime'] > date("Y-m-d h:i:s",strtotime("-12 month")))
				{
					if($this->firstPay($userid)){
						$disc = $money*JDCODE_COUPON_RATIO*2;
					}
					$this->updateJdCodeCoupon($payno, $user['UserName'], ($money*JDCODE_COUPON_RATIO*COUPON_UNIT), $user['JdCode']);
				}else{
				    $this->ppdLog("coupon no add {$userid} no jdcode or user time expired!");
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
    		$data['ObtainDate'] = date("Y-m-d h:i:s",time());
    		$data['ExpireDate'] = $this->prtCfg['END_DATE'];
    		$data['Reason']     = "推荐奖励";
    		$data['UseDate']    = date("Y-m-d h:i:s",time());
    		$data['SN']         = $this->couponSN(3, $coupon);
    		$data['Used']       = 1; /* 立即到账使用 */
    		$data['Type']       = 3; /* 推荐奖励 */

    		$cpnTbl = M("coupon");
    		$record = $cpnTbl->where("PayNo = '{$new_payno}'")->find();
    		if(!$record) {
    			$data['UserId'] = $userid;
    			$status = $cpnTbl->add($data);
    			if($status===false){
    				$this->ppdLog("PayController: user " . $userid . " coupon add ERROR:" .
    						$cpnTbl->getDbError() . " last SQL:". $cpnTbl->getLastSql(),3);
    			}else{
    				$um = M("user");
    				$result=$um->where("UserId='{$userid}'" )->setInc("Score",$coupon);
    				if($result === false){
    					$this->ppdLog("jdcode coupon store faild. UserId:$userid");
    				}
    				$this->ppdLog("PayController: user " . $userid . " coupon add SUCCESS!", 0);
    			}
    		}else{
    			$status = $cpnTbl->where("PayNo= '{$new_payno}'")->save($data);
    			if($status===false)
    				$this->ppdLog("PayController: user " . $userid. "coupon save ERROR:" .
    						$cpnTbl->getDbError() . " last SQL:". $cpnTbl->getLastSql(),3);
    			else
    				$this->ppdLog("PayController: user " . $userid. " coupon save SUCESS!", 0);
    		}
    		$this->ppdLog("PayController: user " . $userid .  " get coupon " . $data['TotalQuota'] . " DB", 0);
    	}
    }
    

    private function updateCoupon($userid, $payno, $coupon) {
    	if ($coupon > 0){
	        $data['PayNo']      = $payno;
	        $data['TotalQuota'] = $coupon;
	        $data['ObtainDate'] = date("Y-m-d h:i:s",time());
	        $data['ExpireDate'] = $this->prtCfg['END_DATE']; 
	        $data['Reason']     = "充值优惠";
	        $data['UseDate']    = date("Y-m-d h:i:s",time());
	        $data['SN']         = $this->couponSN(1, $coupon);
	        $data['Used']       = 1; /* 立即到账使用 */
	        $data['Type']       = 1; /* 非返券形式 */
	        $data['UserId'] 	= $userid;
	
	        $cpnTbl = M("coupon");
	        $record = $cpnTbl->where("Userid = '{$userid}' and PayNo ='{$payno}'")->find();
	        if(!$record) {
	            $status = $cpnTbl->where("Userid = '{$userid}' and PayNo ='{$payno}'")->add($data);
	            if($status===false)
	                $this->ppdLog("PayController: user " . $userid . " coupon add ERROR:" .
	                $cpnTbl->getDbError() . " last SQL:". $cpnTbl->getLastSql(),3);
	            else
	                $this->ppdLog("PayController: user " . $userid . " coupon add SUCCESS!", 0);
	        }else{
	            $status = $cpnTbl->where("Userid = '{$userid}' and PayNo ='{$payno}'")->save($data);
	            if($status===false)
	                $this->ppdLog("PayController: user " . $userid. "coupon save ERROR:" .
	                $cpnTbl->getDbError() . " last SQL:". $cpnTbl->getLastSql(),3);
	            else
	                $this->ppdLog("PayController: user " . $userid. " coupon save SUCESS!", 0);
	        }
	        $this->ppdLog("PayController: user " . $userid .  " get coupon " . $data['TotalQuota'] . " DB", 0);
    	}
    }

    private  function sendHttpRequest($url,$method,$params=array()){ 
        if(trim($url)==''||!in_array($method,array('get','post'))||!is_array($params)){ 
            return false; 
        } 
        $curl=curl_init(); 
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);/* 不输出结果到页面 */
        curl_setopt($curl,CURLOPT_HEADER,0 ); 
        switch($method){ 
        case 'get': 
            $str='?'; 
            foreach($params as $k=>$v){ 
                $str.=$k.'='.$v.'&'; 
            } 
            $str=substr($str,0,-1); 
            $url.=$str;
            curl_setopt($curl,CURLOPT_URL,$url); 
            break; 
        case 'post': 
            curl_setopt($curl,CURLOPT_URL,$url); 
            curl_setopt($curl,CURLOPT_POST,1 ); 
            curl_setopt($curl,CURLOPT_POSTFIELDS,$params); 
            break; 
        default: 
            $result=''; 
            break; 
        } 
        if(!isset($result)){ 
            $result=curl_exec($curl); 
        } 
        curl_close($curl); 
        return $result; 
    } 


}
