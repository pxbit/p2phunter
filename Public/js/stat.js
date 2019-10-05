function getxy(data, type){
  var xdata  = new Array();
  var ydata  = new Array();
  var xyarr  = new Array();      //二维数组对象
  var dtobj  = $.parseJSON(data);//二维数组对象

  xyarr = dtobj[type];
  for (val in xyarr) {
    xdata.push(val);    //取出值，如0.1
    ydata.push(xyarr[val]);//取出键名,如7日
  }
  xydata = new Array(xdata, ydata);
  return xydata;
}

function getOverdue(data, type) {
  var xydata = getxy(data, type);
  var xdata  = xydata[0];
  var ydata  = xydata[1];
  var chartData = {
    labels : xdata,
    datasets : [
      {
        //统计表的背景颜色
        fillColor : "rgba(0,0,255,0.5)",
        //统计表画笔颜色
        strokeColor : "#f60",
        //点的颜色
        pointColor : "#000;",
        //点边框的颜色
        pointStrokeColor : "red",
        //鼠标触发时点的颜色
        pointHighlightFill : "red",
        //鼠标触发时点边框的颜色
        pointHighlightStroke : "#000",
        data : ydata
      }]
  };
  return chartData;
}

$(document).ready(function(){
  $('nav ul.nav > li').click(function (e) {
    e.preventDefault();
    $('ul.nav > li').removeClass('active');
    $(this).addClass('active');
  });

  $.post(Think.U('Home/Statistics/respondAjaxStatData'),
    {}, 
    function(data,status){
      var chartData;
      var ctx;

      chartData = getOverdue(data, 'byday');
      ctx = document.getElementById("canvas-byday").getContext("2d");
      window.myLine = new Chart(ctx).Line(chartData, { responsive: true });

      chartData = getOverdue(data, 'byphase');
      ctx = document.getElementById("canvas-byphase").getContext("2d");
      window.myLine = new Chart(ctx).Bar(chartData, { responsive: true });
    });

});//document_ready end


