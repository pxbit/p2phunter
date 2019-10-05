<?php
namespace Home\Controller;
use Think\Controller;

define('APP_DEBUG',true);
// 定义应用目录
define('APP_PATH','../../../Application/');
require('../../../ThinkPHP/ThinkPHP.php');

class IndexController extends Controller {
    function test(){
    	echo 123;
	}
}
?>
