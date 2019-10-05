<?php
namespace Home\Controller;
use Think\Controller;
class HelpController extends BaseController {


    public function _initialize(){
        //$this->isUserLogin();
    }
    public function index($mobile=0){
        $this->setAccessType();
        $this->displayPage($mobile, "default/help", "default/help_m");
  }


}
