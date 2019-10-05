<?php
class IndexTest extends PHPUnit\Framework\TestCase{
    //构造函数
    function __construct(){
    	//定义TP的版本
    	define('TPUNIT_VERSION','3.2.3');
        //定义目录路径，最好为绝对路径
    	define('TP_BASEPATH', '/Deploy/web/p2phunter');
		//导入base库
		include_once '/Deploy/web/p2phunter/tpunit/base.php';
		//导入要测试的控制器
		include_once '/Deploy/web/p2phunter/Application/Home/Test/IndexController.php';
    }
	//测试index动作
    public function testIndex(){
    	//新建控制器
        $index=new \Home\Controller\IndexController();
		//调用控制器的方法
		$index->test();
		//断言
		$this->expectOutputString('123');
    }
}
?>
