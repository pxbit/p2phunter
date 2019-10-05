<?php
namespace Home\Controller;
use Think\Controller;

class UserBidController extends BaseController {
    public function _initialize(){
      $url  = __ROOT__ . "/home/userbid/";
      $urlPrefix   = __ROOT__ . "/home/userbid/operate?type=";
      $this->assign('bidListUrl',     $urlPrefix . "1");
      $this->assign('bidInterestUrl', $urlPrefix . "2");
      $this->assign('bidOverduetUrl', $urlPrefix . "3");
      $this->assign('mybidurl', $url);
      $this->assign('statisticsUrl', __ROOT__."/home/statistics");
    }

    public function index(){

      if(isset($type)){
        $type = 1;
        $this->assign("type", $type);
      }
      $this->display('default/userbid');
    }

    public function listBid(){
      $userid=$this->isCookieValid();
      if(!$userid){
        $userid=$this->isUserLogin();
        $this->ppdLog("UserBidController/listBid: user didnot login.");
        return null;
      }

      $draw   = I('post.draw');
      $start  = I('post.start');
      $length = I('post.length');

      $bid = M("bid");
      $field   = array('BidId', 'StrategyId','BidAmount','BidTime',"RepayStatus", "BidCost");
      $pageBid = $bid->where("UserId={$userid}")->field($field)->limit($start.",".$length)->select();

      $totalCnt        = $bid->where("UserId={$userid}")->count(); 
      $recordsTotal    = $totalCnt;
      $recordsFiltered = $totalCnt;

      $dt = array();
      for($i = 0; $i < count($pageBid); $i++) {
        /* 
          0：等待还款 
          1：准时还款 
          2：逾期还款 
          3：提前还款 
          4：部分还款
         */
        if($pageBid[$i]['RepayStatus'] == 1)
          $pageBid[$i]['RepayStatus'] = "准时还款";
        $dt[] = (array_values($pageBid[$i]));
      }

      $dtRespond = array(
        "draw"            => intval($draw),
        "recordsTotal"    => intval($recordsTotal),
        "recordsFiltered" => intval($recordsFiltered),
        "data"            => $dt
      );
      echo json_encode($dtRespond);
    }

    public function interestBid(){
      $userid=$this->isCookieValid();
      if(!$userid){
        $userid=$this->isUserLogin();
        $this->ppdLog("UserBidController/interestBid: user didnot login.");
        return null;
      }

      $draw   = I('post.draw');
      $start  = I('post.start');
      $length = I('post.length');

      $bid = M("lpr");
      $field   = array('StrategyId','ListingId','OrderId','RepayDate', 'RepayPrincipal',
        'RepayInterest', 'RepayStatus');
      $pageBid = $bid->where("UserId={$userid} and RepayInterest>0")->field($field)->limit($start.",".$length)->select();

      $totalCnt        = $bid->where("UserId={$userid}")->count(); 
      $recordsTotal    = $totalCnt;
      $recordsFiltered = $totalCnt;

      $dt = array();
      for($i = 0; $i < count($pageBid); $i++) {
        /*
          0：等待还款
          1：准时还款
          2：逾期还款
          3：提前还款
          4：部分还款
         */
        if($pageBid[$i]['RepayStatus'] == 1){
          $pageBid[$i]['RepayStatus'] = "准时还款";
        } elseif($pageBid[$i]['RepayStatus'] == 3){
          $pageBid[$i]['RepayStatus'] = "提前还款";
        }
        $dt[] = (array_values($pageBid[$i]));
      }

      $dtRespond = array(
        "draw"            => intval($draw),
        "recordsTotal"    => intval($recordsTotal),
        "recordsFiltered" => intval($recordsFiltered),
        "data"            => $dt
      );
      echo json_encode($dtRespond);

    }

    public function interestOverview(){
      $userid=$this->isCookieValid();
      if(!$userid){
        $userid=$this->isUserLogin();
        $this->ppdLog("UserBidController/listBid: user didnot login.");
        return null;
      }

      $bid=M("bid");
      $lpr=M("lpr");
      $ow['totalBidAmount'] = $bid->where("UserId='{$userid}'")->sum('BidAmount')+0;
      $ow['totalBidCount']  = $bid->where("UserId='{$userid}'")->count();
      $ow['totalBidGain']   = $lpr->where("UserId='{$userid}'")->sum('RepayInterest')+0;
      $ow['totalGainRatio'] = round(($ow['totalBidGain']/$ow['totalBidAmount'])*100.0, 2);
      $ow['totalRepayCount']= $lpr->where("UserId='{$userid}' and RepayInterest>0")->count();
      $ow['totalRepayBidCount']= $lpr->where("UserId='{$userid}' and RepayInterest>0")->distinct(true)->count();
      return $ow;
    }

    public function operate($type){
    //j  if($type == 1){
    //j    $this->overview();
    //j  }
      $this->assign("type", $type);
      if($type == 2){
        $interestOw = $this->interestOverview();
        $this->assign('interestOw', $interestOw);
      }
      $this->display("default/userbid");
    }

}




?>
