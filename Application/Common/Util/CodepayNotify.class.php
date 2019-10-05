<?php
namespace Common\Util;
use Common\Util\CodepayInterface as CI;
class CodepayNotify {
	var $codepay_config;
	
	function __construct($codepay_config)
	{
		$this->codepay_config = $codepay_config;
	}
	
	function CodepayNotify($codepay_config)
	{
		$this->__construct($codepay_config);
	}
	
	/**
	 * 针对GET及POST验证消息是否是码支付发出的合法消息
	 * @return 验证结果
	 */
	function verifyAll()
	{
		if (!empty($_POST)) {//判断POST来的数组是否为空
			foreach ($_POST as $key => $data) {
				$_GET[$key] = $data;
			}
		}
		if (!empty($_GET)&&$this->getSignVeryfy($_GET, $_GET["sign"])) {
			return true;
		} else {
			return false;
		}
	
	}
	
	/**
	 * 针对notify_url验证消息是否是码支付发出的合法消息
	 * @return 验证结果
	 */
	function verifyNotify()
	{
		if (empty($_POST)) {//判断POST来的数组是否为空
			//logResult('POST为空'); //check before verify
			return false;
		} else {
			if ($this->getSignVeryfy($_POST, $_POST["sign"])) {
				return true;
	
			} else {
				return false;
			}
		}
	}
	
	/**
	 * 针对return_url验证消息是否是码支付发出的合法消息
	 * @return 验证结果
	 */
	function verifyReturn()
	{
		if (empty($_GET)) {//判断POST来的数组是否为空
			return false;
		} else {
			//生成签名结果
			$isSign = $this->getSignVeryfy($_GET, $_GET["sign"]);
			if ($isSign) {
				return true;
			} else {
				return false;
			}
		}
	}
	
	/**
	 * 获取返回时的签名验证结果
	 * @param $para_temp 通知返回来的参数数组
	 * @param $sign 返回的签名结果
	 * @return 签名验证结果
	 */
	function getSignVeryfy($para_temp, $sign)
	{
		//除去待签名参数数组中的空值和签名参数
		$para_filter = CI::paraFilter($para_temp);
		//对待签名参数数组排序
		$para_sort = CI::argSort($para_filter);
		//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
		$prestr = CI::createLinkstring($para_sort);
	
		$isSgin = CI::md5Verify($prestr, $sign, $this->codepay_config['key']);
		return $isSgin;
	}
	

}

?>