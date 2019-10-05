<?php
namespace Home\Controller;
use Think\Controller;
class IndexController extends BaseController {
    public function index(){
        $user_id=$this->isCookieValid();
        if(!$user_id)
            $user_id=$this->isUserLogin();
        if($user_id){
            $loginAction="已经登录";
            $loginTips="您已经授权登录，请点击进入用户中心！";
            $loginUrl="http://www.p2phunter.cn/Home/User/index.html";
            $loginButton="立刻前往";
        }else{
            $loginAction="登录授权";
            $loginTips="如果已有拍拍贷账户，请登录授权";
            $loginUrl="https://ac.ppdai.com/oauth2/login?AppID=xxxxxxxx&ReturnUrl=http://www.p2phunter.cn/home/user/ppd";
            $loginButton="立即登录";
            
        }
        $regUrl = __ROOT__."/home/UserReg/reg";
        $this->assign("loginAction",$loginAction);
        $this->assign("loginTips",$loginTips);
        $this->assign("loginUrl",$loginUrl);
        $this->assign("regUrl",$regUrl);
        $this->assign("loginButton",$loginButton);
        
        $user_count=$this->getUserCount();
        $curent_user_count=$this->getCurrentUserCount();
        //$bid_amount=$this->getTotalBidAmount();
        //$bid_count=$this->getTotalBidCount();
        $this->assign("user_count",$user_count);
        //$this->assign("bid_amount",$bid_amount);
        $this->assign("curent_user_count",$curent_user_count);
        //$this->assign("bid_count",$bid_count);
        $this->display();
    }
    public function getUserCount(){
        $user=M("user");
        $count=$user->where(1)->cache(3600)->count();
        if($count===false){
            $this->ppdLog("getUserCount ERROR:" . $user->getDbError(),2);
            return 1000;
        }else{
            return $count;
        }
    }
    public function getCurrentUserCount(){
        $user=M("user");
        $count=$user->where("ATExpireDate>0")->cache(3600)->count();
        if($count===false){
            $this->ppdLog("getUserCount ERROR:" . $user->getDbError(),2);
            return 1000;
        }else{
            return $count;
        }
    }
    public function getTotalBidAmount(){
        $bid=M("bid");
        $amount=$bid->where("UserId != '10'")->sum('BidAmount');
        if($amount===false){
            $this->ppdLog("getTotalBidAmount ERROR:" . $bid->getDbError(),2);
            return 50000;
        }else{
            return $amount;
        }
    
    }
    public function getTotalBidCount(){
        $bid=M("bid");
        $amount=$bid->where("UserId != '10'")->count();
        if($amount===false){
            $this->ppdLog("getTotalBidAmount ERROR:" . $bid->getDbError(),2);
            return 50000;
        }else{
            return $amount;
        }
    
    }
    
    public function getUserCountLine()
    {
        $str="";
        $user=M("user");
        for($d=90;$d>=0;$d--){
            $timestamp=date("Y-m-d H:i:s", (time()-$d*24*3600));
            $count=$user->where("CreateTime<='{$timestamp}'")->count();
            $str =$str . $count . ",";
        }
        echo $str;
    }

    public function test () {
        if( IS_POST ) {
            $title = I('title', '', 'htmlspecialchars');
            $this->ajaxReturn($title, '成功', 1);
        } else {
            $this->display();
        }
    }
}
    
