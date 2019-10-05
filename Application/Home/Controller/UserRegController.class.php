<?php
namespace Home\Controller;
use Think\Controller;
use Think\Image;

define(ERR_REG_SUCCESS                , 0);
define(ERR_REG_REMOTE_SYS_EXCEPT      , -1);
define(ERR_REG_MOBILE_AND_EMAIL_NULL  , -100003101);
define(ERR_REG_USERROLE_INFO_ERROR    , -100003102);
define(ERR_REG_USERROLE_ERROR         , -100003201);
define(ERR_REG_USERNAME_NULL          , -100003202);
define(ERR_REG_USERNAME_FORMAT_ERROR  , -100003203);
define(ERR_REG_USERNAME_EXIST         , -100003204);
define(ERR_REG_MOBILE_FORMAT_ERROR    , -100003205);
define(ERR_REG_MOBILE_EXIST           , -100003206);
define(ERR_REG_APPID_VERIFY_ERROR     , -100003301);
define(ERR_REG_USERINFO_ERROR         , -100003302);
define(ERR_REG_USER_APP_BIND_ERROR    , -100003303);
define(ERR_REG_USER_LOGIN_FAIL        , -100003304);
define(ERR_REG_EMAIL_FORMAT_ERROR     , -100003401);
define(ERR_REG_EMAIL_EXIST            , -100003402);

/* 云片短信返回值说明：
code = 0: 正确返回。可以从api返回的对应字段中取数据。
code > 0: 调用API时发生错误，需要开发者进行相应的处理。
0 < code < -50: 权限验证失败，需要开发者进行相应的处理。
code = -50: 系统内部错误，请联系技术支持，调查问题原因并获得解决方案。
 */
define(ERR_MOBILE_VER_SUCCESS         ,   0);
define(ERR_MOBILE_VER_FAIL            , -51);
define(ERR_MOBILE_OPER_FRE            , -52); //频繁操作发送验证码


class UserRegController extends UserController
{
    public function _initialize()
    {
        //keep void
    }

    public function reg()
    {
        $mobile = I('post.mobile');
        if (empty($mobile)) //如果不是从reg.html跳过来，就显示reg.html页面
        {
            $regUrl = "reg";
            $this->assign("regUrl",$regUrl);
            $verifyUrl = "verify";
            $this->assign("verifyUrl",$verifyUrl);
            $startReg = true;
            $this->assign("startReg", $startReg);
            $this->display("default/reg");
        }
        else //将reg.html提交过来的数据送给ppd处理
        {
            $url      = "https://openapi.ppdai.com/auth/registerservice/register";
            /* 用户角色：借出者-4，借入者-8 */
            $request  = json_encode(array('Mobile'=> $mobile, 'Email' => "", 'Role'=> 4));
            $result   = parent::send($url, $request);
            /* 获得注册反馈信息 */
            $returnCode = $result["ReturnCode"];
            $returnMsg  = $result["ReturnMessage"];
            /* 注册成功后，ppd返回的MSG为NULL */
            if (ERR_REG_SUCCESS == $returnCode){
                $returnMsg = "来自小雕的喜讯：你手机号已经成功注册。";
                /* 将注册信息写入到数据库 */
                $m = M("user");
                $m->RegSrc       = 1;   /* 0:from ppd; 1: local reg*/
                $m->UserName     = $result['UserName'];
                $m->OpenID       = $result['OpenID'];
                $m->AccessToken  = $result['AccessToken'];
                $m->RefreshToken = $result['RefreshToken'];
                $m->ATExpireDate = date("Y-m-d h:i:s",time()+$result['ExpiresIn']);
                $m->RTExpireDate = date("Y-m-d h:i:s",time()+3600*24*90);
                $m->CreateTime   = date("Y-m-d h:i:s",time());
                $m->add();
            }

            $startReg   = false;
            $this->assign("startReg", $startReg);
            $this->assign("echoCode", $returnCode);
            $this->assign("echoMsg",  $returnMsg);
            $this->display("default/reg");
        }
    }

    public function errorMsgHandle($err)
    {
        switch($err)
        {
        case ERR_REG_SUCCESS                :
            $msg = "恭喜您，注册成功!";
            break;
        case ERR_REG_REMOTE_SYS_EXCEPT      :
            $msg = "拍拍贷系统异常";
            break;
        case ERR_REG_MOBILE_AND_EMAIL_NULL  :
            $msg = "手机和邮箱不能同时为空";
            break;
        case ERR_REG_USERROLE_INFO_ERROR    :
            $msg = "用户角色信息异常";
            break;
        case ERR_REG_USERROLE_ERROR         :
            $msg = "用户角色错误";
            break;
        case ERR_REG_USERNAME_NULL          :
            $msg = "用户名不能为空";
            break;
        case ERR_REG_USERNAME_FORMAT_ERROR  :
            $msg = "用户名格式不正确";
            break;
        case ERR_REG_USERNAME_EXIST         :
            $msg = "用户名已被注册";
            break;
        case ERR_REG_MOBILE_FORMAT_ERROR    :
            $msg = "手机号格式不正确";
            break;
        case ERR_REG_MOBILE_EXIST           :
            $msg = "手机号已被注册";
            break;
        case ERR_REG_APPID_VERIFY_ERROR     :
            $msg = "AppID 验证错误";
            break;
        case ERR_REG_USERINFO_ERROR         :
            $msg = "用户信息错误";
            break;
        case ERR_REG_USER_APP_BIND_ERROR    :
            $msg = "用户与App绑定失败";
            break;
        case ERR_REG_USER_LOGIN_FAIL        :
            $msg = "用户登录App失败";
            break;
        case ERR_REG_EMAIL_FORMAT_ERROR     :
            $msg = "邮箱格式不正确";
            break;
        case ERR_REG_EMAIL_EXIST            :
            $msg = "邮箱已被注册";
            break;
        default:
            $msg = "错误未知";
        }
        return $msg;
    }

    /* 生成验证码 */
    public function verify()
    {
        ob_clean();//清除缓冲区
        $cfg=array(
            'fontSize' => 18,
            'length'   => 4,
            'useNoise' => false,
            'imageW'   => 130,
            'imageH'   => 50,
            'expire'   => 60
        );
        $verify=new \Think\Verify($cfg);
        $verify->fontttf = '4.ttf';
        $verify->entry();
    }

    /* 验证码校验 */
    public function check_verify($code, $id = '')
    {
        $verify = new \Think\Verify();
        $res = $verify->check($code, $id);
        $this->ajaxReturn($res, 'json');
    }

    public function login()
    {
        $isRegisted = true;
        $this->assign("isRegisted",  $isRegisted);
        $this->display("default/login");
    }

    public function checkMobileVerifyCode(){
      $code = I("post.mobileVerifyCode");
      if($code == session(vefyCode))
        $this->ajaxReturn(1);
      else
        $this->ajaxReturn(0);
    }

    public function sendMobileVerifyCode()
    {
        $curTime  = time();
        $timediff = $curTime - session(prevTime);
        $prevTime = $curTime;
        session(prevTime, $prevTime);
        session(curTime,  $curTime);
        if($timediff < 60) /* 1分钟内不能重复发送 */
        {
            $ajaxResp = ERR_MOBILE_OPER_FRE;
            $this->ajaxReturn($ajaxResp);
            return;
        }
        $mobile   = I('post.mobile');
        $vefyCode = strval(rand(1000,9999));
        $smsCode  = $this->verifySend($mobile, $vefyCode);
        $ajaxResp = ($smsCode == 0)? ERR_MOBILE_VER_SUCCESS : ERR_MOBILE_VER_FAIL;
        session(vefyCode, $vefyCode);
        $this->ajaxReturn($ajaxResp);
    }

    public function verifySend($mobile, $vefycode)
    {
        if(empty($mobile) || empty($vefycode))
            return ERR_MOBILE_VER_FAIL;

        header("Content-Type:text/html;charset=utf-8");
        $apikey = "your_api_key";
        $ch = curl_init();

        /* 设置验证方式 */
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept:text/plain;charset=utf-8',
            'Content-Type:application/x-www-form-urlencoded', 'charset=utf-8'));
        /* 设置返回结果为流 */
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        /* 设置超时时间*/
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        /* 设置通信方式 */
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // 取得用户信息
        $json_data = $this->get_user($ch,$apikey);
        $array = json_decode($json_data,true);

        // 发送模板短信
        $tplvalue=(("#code#") . "=" . $vefycode . "&" . ("#company#") . "=" . ("金雕猎手"));
        $data = array('tpl_id' => '1', 'tpl_value' => $tplvalue, 'apikey'=>$apikey, 'mobile'=>$mobile);
        $json_data = $this->tpl_send($ch,$data);
        $array = json_decode($json_data,true);
        curl_close($ch);
        return $array["code"];
    }

    //获得账户
    function get_user($ch,$apikey){
        curl_setopt ($ch, CURLOPT_URL, 'https://sms.yunpian.com/v2/user/get.json');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('apikey' => $apikey)));
        $result = curl_exec($ch);
        $error = curl_error($ch);
        $this->checkErr($result,$error);
        return $result;
    }

    function send($ch,$data){
        curl_setopt ($ch, CURLOPT_URL, 'https://sms.yunpian.com/v2/sms/single_send.json');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $result = curl_exec($ch);
        $error = curl_error($ch);
        $this->checkErr($result,$error);
        return $result;
    }
    function tpl_send($ch,$data){
        curl_setopt ($ch, CURLOPT_URL,
            'https://sms.yunpian.com/v2/sms/tpl_single_send.json');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $result = curl_exec($ch);
        $error = curl_error($ch);
        $this->checkErr($result,$error);
        return $result;
    }

    function voice_send($ch,$data){
        curl_setopt ($ch, CURLOPT_URL, 'http://voice.yunpian.com/v2/voice/send.json');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $result = curl_exec($ch);
        $error = curl_error($ch);
        $this->checkErr($result,$error);
        return $result;
    }

    function notify_send($ch,$data){
        curl_setopt ($ch, CURLOPT_URL, 'https://voice.yunpian.com/v2/voice/tpl_notify.json');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $result = curl_exec($ch);
        $error = curl_error($ch);
        $this->checkErr($result,$error);
        return $result;
    }

    function checkErr($result,$error) {
        if($result === false) {
            echo 'Curl error: ' . $error;
        } else {
            //echo '操作完成没有任何错误';
        }
    }
}


