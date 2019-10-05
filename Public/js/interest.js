function initQueryInterestListTable(table){
  var settings = {};
  settings.ajax_url = Think.U('Home/User/listInterest');
  var config = getDTConfig(settings)
  /* 移动端 */
  if ($("#mbl").val() == 1){
    config.pageLength = 5;
  } else {
    config.pageLength = 10;
  }
  table.dataTable(config);
}

$(document).ready(function(){
  $.post(Think.U('Home/user/ajaxStatInterest'),
    {},
    function(data,status){
      var dtInterest    = parseAjax(data, 2,'interest');
      var dtOverdue     = parseAjax(data, 2, 'overdue');
      var dtNetInterest = parseAjax(data, 2, 'netInterest');
      var settings      = new Object();
      settings.x = dtInterest[0]; //取出x值, 因为是相同的，任选其一
      settings.y = [
        {
          title:  "毛收益",
          data : dtInterest[1],  //取出y值
          color: "#0000ff"
        },
        {
          title : "逾期(30日)",
          data  : dtOverdue[1],
          color : "#ff8002"
        },
        {
          title : "净收益",
          data  : dtNetInterest[1],
          color : "#00CD00"
        }
      ];
      settings.type       = 'line';
      settings.title_text = '收益/逾期增长图';
      settings.xAxesLabel = '日期';
      settings.yAxesLabel = '累计金额';
      plotchart(settings, "canvas_interest_increase");
    });

  $("#downloadInterest").click(function(){
    location.href = ROOT+"/home/user/downloadInterestList";
  });

});//document_ready end


