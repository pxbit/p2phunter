/*******************************************************
 *
 *  提供通用的、公共的、基础类js函数
 *
/*******************************************************/

/* 向服务端请求到的ajax数据的解析。
 * data: 从服务器端得到的ajax数据，三维数组。
 * type: 二维数组的第二维键值，比如数组内容为：
 * data['overdue']  = ['2018-01-01'=> 0.1, '2018-01-02'=> 0.2]
 * data['interest'] = ['2018-01-01'=> 1.0, '2018-01-02'=> 1.1]
 * type 为overdue*/
function parseAjax(data, demension, type){
  var xdata  = new Array();
  var ydata  = new Array();
  var xyarr  = new Array();      //二维数组对象
  var dtobj  = $.parseJSON(data);//二维数组对象

  if(demension == 1){
    xyarr = dtobj;
  }else if(demension == 2){
    xyarr = dtobj[type];
  }

  for (val in xyarr) {
    /* 取出键值,如'2018-01-01' */
    xdata.push(val);
    /* 取出值，保留一位小数,如0.1 */
    ydata.push(xyarr[val].toFixed(2));
  }
  xydata = new Array(xdata, ydata);
  return xydata;
}


function time2str(time){
  var date = new Date();
  date.setTime(time * 1000);
  /*
  console.log(newDate.toLocaleDateString()); // 2014年6月18日
  console.log(newDate.toLocaleString()); // 2014年6月18日 上午10:33:24
  console.log(newDate.toLocaleTimeString()); // 上午10:33:24
  */
  return date.toLocaleString();

}


