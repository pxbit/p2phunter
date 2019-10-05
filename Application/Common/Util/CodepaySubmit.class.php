<?php
namespace Common\Util;
use Common\Util\CodepayInterface as CI;
class CodepaySubmit {

	var $codepay_config;
	/**
	 *码支付订单创建网关地址
	 */
	var $codepay_gateway_new;
	//var $ci;
	
	function __construct($codepay_config)
	{
		//$ci=new CodepayInterface();
		$this->codepay_config = $codepay_config;
		$this->codepay_gateway_new = CI::getApiHost() . 'creat_order/?';
		
	}
	
	function CodepaySubmit($codepay_config)
	{
		$this->__construct($codepay_config);
	}
	
	/**
	 * 生成签名结果
	 * @param $para_sort 已排序要签名的数组
	 * return 签名结果字符串
	 */
	function buildRequestMysign($para_sort)
	{
		//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
		$prestr = CI::createLinkstring($para_sort);
		$mysign = CI::md5Sign($prestr, $this->codepay_config['key']);
		return $mysign;
	}
	
	/**
	 * 生成要请求给码支付的参数数组
	 * @param $para_temp 请求前的参数数组
	 * @return 要请求的参数数组
	 */
	function buildRequestPara($para_temp)
	{
		//除去待签名参数数组中的空值和签名参数
		$para_filter = CI::paraFilter($para_temp);
	
		//对待签名参数数组排序
		$para_sort = CI::argSort($para_filter);
	
		//生成签名结果
		$mysign = $this->buildRequestMysign($para_sort);
	
		//签名结果与签名方式加入请求提交参数组中
		$para_sort['sign'] = $mysign;
		return $para_sort;
	}
	
	/**
	 * 生成要请求给码支付的参数数组
	 * @param $para_temp 请求前的参数数组
	 * @return 要请求的参数数组字符串
	 */
	function buildRequestParaToString($para_temp)
	{
		//待请求参数数组
		$para = $this->buildRequestPara($para_temp);
		//把参数组中所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串，并对字符串做urlencode编码
		$request_data = CI::createLinkstringUrlencode($para);
		return $request_data;
	}
	
	/**
	 * 建立请求，以表单HTML形式构造（默认）
	 * @param $para_temp 请求参数数组
	 * @param $method 提交方式。两个值可选：post、get
	 * @param $button_name 确认按钮显示文字
	 * @return 提交表单HTML文本
	 */
	function buildRequestForm($para_temp, $method, $button_name)
	{
		//待请求参数数组
		$para = $this->buildRequestPara($para_temp);
		$sHtml = "<form id='codepaysubmit' name='codepaysubmit' action='" . $this->codepay_gateway_new . "creatTime=" . time() . "' method='" . $method . "'>";
		while (list ($key, $val) = each($para)) {
			$sHtml .= "<input type='hidden' name='" . $key . "' value='" . $val . "'/>";
		}
		//submit按钮控件请不要含有name属性 否则签名不会通过
		$sHtml = $sHtml . "<input type='submit'  value='" . $button_name . "' style='display:none;'></form>";
		$sHtml = $sHtml . "<script>document.forms['codepaysubmit'].submit();</script>";
		return $sHtml;
	}
}

?>