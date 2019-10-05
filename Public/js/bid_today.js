
$(document).ready(function(){
  $.post(Think.U('Home/user/ajaxStatBidDistToday'),
    {},
    function(data,status){
      /* 风险等级分布*/
      var riskData = parseAjax(data, 2, 'risk');
      var settings = new Object();
      settings.x = riskData[0];
      settings.y = [
        {
          data : riskData[1],
          color : "#ff8002",
        },
      ];
      settings.type = 'bar';
      settings.title_display  = true;
      settings.title_text     = "";
      settings.xAxesLabel     = '风险等级';
      settings.yAxesLabel     = '用户投标数';
      settings.legend_display = false;
      plotchart(settings, "canvas-riskrank");

      /* 学历分布 */
      var eduDegreeData = parseAjax(data, 2, 'eduDegree');
      var settings      = new Object();
      settings.x = eduDegreeData[0];
      settings.y = [
        {
          data : eduDegreeData[1],
          color : "#ff8002",
        },
      ];
      settings.type = 'bar';
      settings.title_display  = true;
      settings.title_text     = "";
      settings.xAxesLabel     = '学历等级';
      settings.yAxesLabel     = '用户投标数';
      settings.legend_display = false;
      plotchart(settings, "canvas-edudegree");

      /*策略分布*/
      var strategyData  = parseAjax(data, 2, 'strategy');
      var settings = new Object();
      settings.x = strategyData[0];
      settings.y = [
        {
          data  : strategyData[1],
          color : "#ff8002",
        },
      ];
      settings.type           = 'bar';
      settings.title_display  = true;
      settings.title_text     = "";
      settings.xAxesLabel     = '策略名称';
      settings.yAxesLabel     = '用户投标数';
      settings.legend_display = false;
      plotchart(settings, "canvas-strategy");

      /*年龄分布*/
      var ageData  = parseAjax(data, 2, 'age');
      var settings = new Object();
      settings.x = ageData[0];
      settings.y = [
        {
          data  : ageData[1],
          color : "#ff8002",
        },
      ];

      settings.type = 'bar';
      settings.title_display  = true;
      settings.title_text     = "";
      settings.xAxesLabel     = '年龄段';
      settings.yAxesLabel     = '用户投标数';
      settings.legend_display = false;
      plotchart(settings, "canvas-age");

    });

  $.post(Think.U('Home/user/ajaxBidAmountToday'),
    {},
    function(data,status){
      var amtPulse = parseAjax(data, 2,'amtPulse');
      var amtCumu  = parseAjax(data, 2,'amtCumu');
      var settings = new Object();
      settings.x = amtPulse[0]; //取出x值, 因为是相同的，任选其一
      settings.y = [
        {
          title : "瞬时投标量",
          data  : amtPulse[1],  //取出y值
          color : "#ff8002",
        },
        {
          title : "累积投标量",
          data  : amtCumu[1],  //取出y值
          color : "#0000ff"
        },
      ];
      settings.type       = 'line';
      settings.title_text = "今日投标量分布";
      settings.xAxesLabel = '时间';
      settings.yAxesLabel = '投标量(单)';
      plotchart(settings,"canvas-bidamount");

    });
});//document_ready end


