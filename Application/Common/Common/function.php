<?php
function file_put_vardump($file, $val, $append){
  ob_start();
  var_dump($val);
  $content = ob_get_clean();
  if($append)
    file_put_contents($file, $content, FILE_APPEND);
  else
    file_put_contents($file, $content);
}

/**
 * 输出变量
 *
 * @param void $varVal 变量值
 * @param str $varName 变量名
 * @param bool $isExit 是否输出变量之后就结束程序（TRUE:是 FALSE:否）
 */
function gdump($varVal, $isExit = FALSE){
    ob_start();
    var_dump($varVal);
    $varVal = ob_get_clean();
    $varVal = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $varVal);
    echo '<pre>'.$varVal.'</pre>';
    $isExit && exit();
}

?>
