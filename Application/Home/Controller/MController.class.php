<?php
namespace Home\Controller;
use Think\Controller;
class MController extends BaseController {
    public function index(){
    	$this->display("default/mlogin");
    }
}