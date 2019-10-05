<?php
namespace Home\Controller;
use Think\Controller;
use JpGraph\JpGraph;
use Monolog;
use Requests;

const INVALID_DELAY_RATIO = 0xffff;

class StatisticsController extends BaseController {
    public function _initialize(){
      $urlPrefix   = __ROOT__ . "/home/statistics/operate?type=";
      $this->assign('overviewUrl', $urlPrefix . "1");
      $this->assign('overdueUrl' , $urlPrefix . "2");
      $this->assign('interestUrl', $urlPrefix . "3");
      $this->assign('bidUrl'     , $urlPrefix . "4");
      $this->assign('strategyUrl', $urlPrefix . "5");
    }

    public function index(){
        $this->assign('type', 1);
        $this->display('default/statistics');
    }

    public function plotOverdueRatio(){
        $userid=$this->isCookieValid();
        /* user just login. cookie not send.*/
        if(!$userid){
            $userid=$this->isUserLogin();
            $this->ppdLog("Statistic/plotOverdueRatio: user didnot login");
            return null;
        }

        /* JpGraph init */
        JpGraph::load();
        JpGraph::module('line'); 
        JpGraph::module('bar'); 
        $width  = 600;
        $height = 400;
        $graph  = new \Graph($width,$height);
        $graph->SetScale('textlin');
        $graph->SetMargin(50,30,30,60);
        $graph->title->Set($this->mbConvert("用户{$userid}的延期率数据柱状图"));
        $graph->title->SetFont(FF_SIMSUN,FS_BOLD);
        $graph->xaxis->title->Set($this->mbConvert('时间'));
        $graph->xaxis->title->SetFont(FF_SIMSUN,FS_BOLD);
        $graph->yaxis->title->Set($this->mbConvert('延期率'));
        $graph->yaxis->title->SetFont(FF_SIMSUN,FS_BOLD);
        $graph->yaxis->SetTitleMargin(30);
        $halfWeek  = $this->calcDelayRatio($userid, 3);
        $oneWeek   = $this->calcDelayRatio($userid, 7);
        $twoWeek   = $this->calcDelayRatio($userid, 14);
        $threeWeek = $this->calcDelayRatio($userid, 20);
        $oneMoth   = $this->calcDelayRatio($userid, 30);
        $ratio     = array($halfWeek, $oneWeek, $twoWeek, $threeWeek, $oneMoth);
        $lineplot  = new \BarPlot($ratio);
        $graph->Add($lineplot);
        $graph->stroke();
    }

    public function respondAjaxStatData(){
      $userid=$this->isCookieValid();
      if(!$userid){
        $userid=$this->isUserLogin();
        $this->ppdLog("Statistic/plotOverdueRatio: user didnot login");
        return null;
      }
      $overdueByday['3日']  = $this->calcDelayRatioByDay($userid, 3);
      $overdueByday['7日']  = $this->calcDelayRatioByDay($userid, 7);
      $overdueByday['14日'] = $this->calcDelayRatioByDay($userid, 14);
      $overdueByday['20日'] = $this->calcDelayRatioByDay($userid, 20);
      $overdueByday['30日'] = $this->calcDelayRatioByDay($userid, 30);

      $overdueByphase['第1期'] = $this->calcDelayRatioByPhase($userid, 1);
      $overdueByphase['第2期'] = $this->calcDelayRatioByPhase($userid, 2);
      $overdueByphase['第3期'] = $this->calcDelayRatioByPhase($userid, 3);
      $overdueByphase['第4期'] = $this->calcDelayRatioByPhase($userid, 4);
      $overdueByphase['第5期'] = $this->calcDelayRatioByPhase($userid, 5);
      $overdueByphase['第6期'] = $this->calcDelayRatioByPhase($userid, 6);

      $statOverdue['byday']   = $overdueByday;
      $statOverdue['byphase'] = $overdueByphase;
      $ajax = json_encode($statOverdue, JSON_UNESCAPED_UNICODE);
      $this->ajaxReturn($ajax);
    }

    public function calcDelayRatioByDay($uid, $day) {
        if($uid < 0){
            $this->ppdLog("Statistic/calcDelayRatio: userid({$uid}) is invalid", 3);
            return INVALID_DELAY_RATIO;
        }

        $lpr           = M("lpr");
        $ago           = date("Y-m-d H:i:s", time() - 3600 * 24 * $day);
        $selStr        = "UserId=" . $uid . " and RepayInterest>0";
        $totalRepayCnt = $lpr->where($selStr)->count();

        $selStr        = "UserId=" . $uid . " and OverdueDays>" . $day ." and RepayStatus=0 and UpdateTime >'{$ago}'";
        $totalDelayCnt = $lpr->where($selStr)->count();
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
        $delayCnt   = $lpr->where($selStr)->count();
        $selStr     = "UserId=" . $uid . " and OrderId={$phase}";
        $totalCnt   = $lpr->where($selStr)->count();
        $delayRatio = round($delayCnt * 100 / ($totalCnt + 0.001), 2);

        $this->ppdLog("Statistic/calcDelayRatioByPhase:user{$uid}'s delayCnt = {$delayCnt}," .
            "totalCnt = {$totalCnt} in {$phase} phase");
        return $delayRatio;
    }

    public function mbConvert($str){
        return mb_convert_encoding($str, "html-entities", "utf-8");
    }

    public function operate($type){
      if($type == 1){
        $this->overview();
      }
      $this->assign("type", $type);
      $this->display("default/statistics");
    }

    public function overview(){
      $userid=$this->isCookieValid();
      if(!$userid){
        $userid=$this->isUserLogin();
        $this->ppdLog("Statistic/plotOverdueRatio: user didnot login");
        return null;
      }

      /* 投标总况 */
      $lpr = M("lpr");
      $bid = M("bid");

      $bidOw = array();
      $totalBidCnt = $bid->where("UserId={$userid}")->count() + 0;
      array_push($bidOw, array('name'=>'投标总数','value'=>$totalBidCnt, 'unit'=>'单'));

      $totalBidAmount = $bid->where("UserId={$userid}")->sum('BidAmount') + 0;
      array_push($bidOw, array('name'=>'投标金额','value'=>$totalBidAmount, 'unit'=>'元'));

      $totalBidCost   = $bid->where("UserId={$userid}")->sum('BidCost') + 0;
      array_push($bidOw, array('name'=>'投标费用','value'=>$totalBidCost, 'unit'=>'雕币'));
      $this->assign('bidOverview', $bidOw);

      /* 收益总况 */
      $gainOw = array();
      $totalGainCnt=$lpr->where("UserId='{$userid}' and RepayInterest>0")->count() + 0;
      array_push($gainOw, array('name'=>'获利标数','value'=>$totalGainCnt, 'unit'=>'单'));

      $totalGainAmount = $lpr->where("UserId='{$userid}'")->sum('RepayInterest') + 0;
      array_push($gainOw, array('name'=>'获利金额','value'=>$totalGainAmount, 'unit'=>'元'));
      $this->assign('gainOverview', $gainOw);

      /* 逾期总况 */
      $overdueOw  = array();
      $overdueDay = 7;
      $weekago    = date("Y-m-d H:i:s",time() - 3600*24*$overdueDay);
      $selStr     = "UserId={$userid} and OverdueDays>{$overdueDay} and RepayStatus=0 and UpdateTime >'{$weekago}'";
      $totalDelayCnt = $lpr->where($selStr)->count() + 0;
      array_push($overdueOw, array('name'=>'逾期标数','value'=>$totalDelayCnt, 'unit'=>'单'));

      $totalDelayAmount = $lpr->where($selStr)->sum('OwingPrincipal') + 0;
      array_push($overdueOw, array('name'=>'逾期金额','value'=>$totalDelayAmount, 'unit'=>'元'));

      $totalDelayRatio = round(($totalDelayCount * 100)/($totalGainCount + 0.001), 2);
      array_push($overdueOw, array('name'=>'逾期率','value'=>$totalDelayRatio, 'unit'=>'%'));
      $this->assign('overdueOverview', $overdueOw);
      /*策略总况 */

      $strategyOw = array();
      $m = M("strategy_setting");
      $sysCnt = $m->where("UserId='{$userid}' AND BidAmount >=50")->count();
      array_push($strategyOw, array('name'=>'系统策略','value'=>$sysCnt, 'unit'=>'个'));

      $m = M("personal_strategy");
      $selfCnt = $m->where("UserId='{$userid}' AND BidAmount >=50")->count();
      array_push($strategyOw, array('name'=>'自定义策略','value'=>$selfCnt, 'unit'=>'个'));
      $this->assign('strategyOverview', $strategyOw);
    }

    public function testCalcDelayRatio(){
        for($i = 0; $i < 200; $i++){
            $uid   = rand(-1,2000);
            for($j = 0; $j < 10; $j++) {
                $phase   = rand(-1,10);
                echo  "ByPhase:[{$uid},{$phase}]" . "->" . $this->calcDelayRatioByPhase($uid, $phase);
                echo "</br>";
            }
        }
        for($i = 0; $i < 200; $i++){
            $uid   = rand(-1,2000);
            for($j = 0; $j < 10; $j++) {
                $day   = rand(-1,60);
                echo  "ByDay:[{$uid},{$day}]" . "->" . $this->calcDelayRatioByDay($uid, $day);
                echo "</br>";
            }
        }
    }

    public function sendRequest(){
        requests::register_autoloader();
        $postdata = '{"sign":"4f96fe8050e288acc7d7bf75d5ecbe44",
            "codepay_server_time":"1513556893",
            "endTime":"1513222384",
            "id":"173178522887568",
            "mode":"0",
            "money":"20.00",
            "notify_count":"1",
            "pay_id":"554",
            "pay_name":"c809e57aea3c618da2e9a55ff6cfe41d",
            "pay_no":"1000050301171214012205000062012951942230",
            "pay_time":"1513222384","price":"20.00",
            "status":"1",
            "tag":"0",
            "target":"get",
            "trueID":"12733",
            "type":"3",
            "userID":"12733"}';
        $data = json_decode($postdata,true); //强制转换为数组
        $url="http://".$_SERVER['SERVER_NAME'].":".$_SERVER['SERVER_PORT'] . '/p2phunter/home/pay/notify';
        $response = Requests::post($url, array(), $data);
        var_dump($response->body);
    }
}
?>
