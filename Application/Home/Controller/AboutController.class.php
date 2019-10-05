<?php
namespace Home\Controller;
use Think\Controller;
class AboutController extends BaseController {

  public function _initialize(){
    //$this->isUserLogin();
  }
  public function index($mobile=0){
    /* 0: 初始状态. 1: 检测为PC/移动端浏览器访问. 2: 检测为微信访问 */
    if (session('accessType') == 0){
      if (!$this->isWxBrowser()){
        session('accessType', 1);
      } else {
        session('accessType', 2);
      }
    }

    if (session('accessType') == 2){
      /* 微信访问直接跳转到移动页面 */
      $this->assign("mbl", 1);
      $this->display("default/about_m");
    } else {
      if ($mobile == 0){
        $this->assign("mbl", 0);
        $this->display("default/about");
      } else {
        $this->assign("mbl", 1);
        $this->display("default/about_m");
      }
    }
  }

}
