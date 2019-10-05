<?php
namespace Home\Controller;
use Think\Controller;

class VolumeController extends AutobidController{
    public function __construct(){
        parent::__construct();
    }

    public function getBidVolume($day){
        $day = date("Y-m-d",strtotime("-1 day"));
        $res = $this->getVolumeList(1, $day);
        var_dump($res['data']['list']);

        $userid     = 1;
        $list_id    = '111';
        $strategyid = 1000;
        $bidamount  = 50;
        //$this->biddingRecord($user_id,$list_id,$strategyid, $bidamount);
    }

}

?>

