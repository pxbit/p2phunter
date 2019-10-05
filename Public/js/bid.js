
$(document).ready(function(){
  $.post(Think.U('Home/user/ajaxStatBidDist'),
    {},
    function(data,status){
      /* 风险等级分布*/
      var riskData = parseAjax(data, 2,'risk');
      var settings = new Object();
      settings.x = riskData[0];
      settings.y = [
        {
          data : riskData[1],
          color: ["#80fc9c","#ecfb36","#f8b22b"],
        },
      ];
      settings.type = 'pie';
      plotchart(settings, "canvas-riskrank");

      /* 学历分布 */
      var eduDegreeData  = parseAjax(data, 2, 'eduDegree');
      var settings       = new Object();
      settings.x = eduDegreeData[0];
      settings.y = [
        {
          data : eduDegreeData[1],
          color: ["#80fc9c","#ecfb36","#f8b22b"],
        },
      ];
      settings.type = 'pie';
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
      if (settings.y[0].data.length > 4){
        settings.type          = 'bar';
        settings.title_display = true;
        settings.title_text    = "";
        settings.xAxesLabel    = '年龄段';
        settings.yAxesLabel    = '用户投标数';
      } else {
      settings.y[0].color=["#80fc9c","#ecfb36","#f8b22b", "#55bb9f"];
        settings.type           = 'pie';
      }
      plotchart(settings, "canvas-age");

    });

  $.post(Think.U('Home/user/ajaxBidAmount'),
    {},
    function(data,status){
      var bidData = parseAjax(data, 2,'day');
      var settings = new Object();
      settings.x =bidData[0]; //取出x值, 因为是相同的，任选其一
      settings.y = [
        {
          title : "每日总共",
          data  : bidData[1],  //取出y值
          color : "#ff8002",
        },
      ];
      settings.type       = 'line';
      settings.title_text = '历史投标量分布';
      settings.xAxesLabel = '日期';
      settings.yAxesLabel = '投标量(单)';
      plotchart(settings,"canvas-bidamount");

    });
});//document_ready end


