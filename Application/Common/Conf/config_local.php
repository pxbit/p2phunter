<?php

 return array(
 		'APP_GROUP_LIST' => 'Home,Admin', //项目分组设定
 		'DEFAULT_GROUP'  => 'Home', //默认分组
 		'VAR_PAGE'=>'p',
 		'PAGE_SIZE'=>15,
 		'DB_TYPE'=>'mysql',
 		'DB_HOST'=>'localhost',
 		'DB_NAME'=>'ppd',
 		'DB_USER'=>'root',
 		'DB_PWD'=>'',
 		'DB_PREFIX'=>'ppd_',
 		'DEFAULT_C_LAYER' =>  'Controller',
 		'DATA_CACHE_SUBDIR'=>true,
 		'DATA_PATH_LEVEL'=>2,
 		'SESSION_PREFIX' => 'PPD',
 		'COOKIE_PREFIX'  => 'PPD',
 		'URL_CASE_INSENSITIVE' => true,
 		'URL_HTML_SUFFIX' => 'html',
		'DATA_CACHE_COMPRESS'   => false,   // 数据缓存是否压缩缓存
		'DATA_CACHE_CHECK'      => false,   // 数据缓存是否校验缓存
		'DATA_CACHE_PREFIX'     => 'ppd_c_',     // 缓存前缀
		'DATA_CACHE_TYPE'       => 'File',  // 数据缓存类型
		'DATA_CACHE_PATH'       => TEMP_PATH,// 缓存路径设置 (仅对File方式有效)
		'AUTOLOAD_NAMESPACE' => array(
				'Lib'     => APP_PATH.'Common'.'Util',
		)
 );
?>
