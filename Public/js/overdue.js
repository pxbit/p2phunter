function plotOverdue(data){
  var settings   = new Object();
  var dtInterest = parseAjax(data,1, '');
  var dtOverdue  = parseAjax(data,1, '');
  var settings   = new Object();
  settings.x = dtInterest[0]; //取出x值, 因为是相同的，任选其一
  settings.y = [
    {
      data : dtOverdue[1],
      color: "#ff8002",
    }
  ];
  settings.type           = 'bar';
  settings.title_display  = true;
  settings.title_text     = '分期逾期分布图';
  settings.xAxesLabel     = '期数';
  settings.yAxesLabel     = '逾期率(%)';
  settings.legend_display = false;
  plotchart(settings,"canvas_overdue_ratio" );
  return;
}

$(document).ready(function(){

  $.post(Think.U('Home/User/getEnabledStrategyNameList'),{},function(data,textStatus){
    var json = PPD.toJson(data);
    if(json['status']==0){
      var id;
      $("#overdue-bystrategy").append('<option value="-1" >所有策略</option>');
      $("#overdue-bystrategy").append('<option value="-2" >系统策略</option>');
      $("#overdue-bystrategy").append('<option value="-3" >自定义策略</option>');
      for (id in json) {
        if(json[id]!=0){
          $("#overdue-bystrategy").append('<option value="' + id  + '">' + json[id] + '</option>');
        }
      }
      $('#overdue-bystrategy option:first').attr('selected','selected');
      $('#overdue-bystrategy').selectmenu("refresh");//这句很重要，否则缺省的选中状态没有效果
    }else{
      alert("网络错误，请刷新重试...");
    }
  });

  $.post(Think.U('Home/user/ajaxStatOverdue'),
    {
      /* 默认情况为30日，所有策略 */
      strategy: -1,
      period:30
    },
    function(data,status){
      /*绘制条形图*/
      plotOverdue(data);
    });

  $("#overdue-bystrategy").selectmenu({
    change: function(event, ui) {
      $.post(Think.U('Home/user/ajaxStatOverdue'),
        {
          strategy: $("#overdue-bystrategy").val(),
          period: $("#overdue-byday").val()
        },
        function(data,status){
          $("#canvas_overdue_ratio").remove();
          $("#canvas_overdue_container").append('<canvas id="canvas_overdue_ratio" ></canvas>');
          /*绘制条形图*/
          plotOverdue(data);
        });
    },
    width:150
  }).selectmenu("menuWidget").addClass("overflow");

  $("#overdue-byday").selectmenu({
    change: function(event, ui) {
      $.post(Think.U('Home/user/ajaxStatOverdue'),
        {
          strategy: $("#overdue-bystrategy").val(),
          period: $("#overdue-byday").val()
        },
        function(data,status){
          $("#canvas_overdue_ratio").remove();
          $("#canvas_overdue_container").append('<canvas id="canvas_overdue_ratio" ></canvas>');
          /*绘制条形图*/
          plotOverdue(data);
        })
    },
    width:130
  });
});//document_ready end


