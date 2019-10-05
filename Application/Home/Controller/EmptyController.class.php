<?php
namespace Home\Controller;
use Think\Controller;
/**
 * 基础控制器
 */
class EmptyController extends Controller {
	public function _empty($p){
		cookie('jdcode',$p,3600*24*15);
		$this->redirect("index/index");
	}
}