
function getDTAjaxPostData(strategyid, qrystart, qryend){
  var start = qrystart;
  var end   = qryend;
  var ajaxData = function ( d ) {
    d.strategyid = strategyid;
    d.qrystart   = start;
    d.qryend     = end;
  }
  return ajaxData;
}

function getQryTime(timeval, dateStart, dateEnd){
  if(timeval != 0){
    var date     = new Date();
    var time     = date.getTime();  /* ms */
    var qrystart = (time/1000) - timeval * 24 *3600;
    var qryend   = time/1000;
  }else{
    var qrystart = Date.parse(dateStart)/1000; /*1000:s => ms */
    var qryend   = Date.parse(dateEnd)/1000;
  }
  return new Array(parseInt(qrystart), parseInt(qryend));
}

function initQueryTable(table){
  var settings   = {};
  var strategyid = $("#StrategyType").val();
  var qrytime    = getQryTime($("#Time").val(), $("#date_start").val(), $("#date_end").val());
  var qrystart   = qrytime[0];
  var qryend     = qrytime[1];

  settings.ajax_data   = getDTAjaxPostData(strategyid, qrystart, qryend);
  settings.ajax_url    = Think.U('Home/User/listBasicBidInfo');
  settings.column_defs = [
      {
        /* The `data` parameter refers to the data for the cell (defined by the
         *`data` option, which defaults to the column being worked with, in this
         * case is the last column */
        data:5,
        "render": function ( data, type, row ) {
          var link= "<a href=\"http://invest.ppdai.com/loan/info?id=" + data + "\" target=\"_blank\" >"+data+"</a>";
          return link;

        },
        "targets":-1
      }
    ];
  var config = getDTConfig(settings);
  /* 移动端 */
  if ($("#mbl").val() == 1){
    config.pageLength = 5;
  } else {
    config.pageLength = 10;
  }
  table.dataTable(config);
}

function initCouponTipLayer(){
  var msgstr   = '<span style="font-size:14px;font-family:Microsoft Yahei;">现在是活动时间段，充值有优惠啦.</span>';
  var titlestr = '<span style="font-size:14px;font-family:Microsoft Yahei;">来自金雕的温馨提示:</span>';
  var btnStr   = '<span style="font-size:14px;font-family:Microsoft Yahei;">知道了</span>';
  var notified = $.cookie('coupon_notify');
  if (notified != 'yes') {
    $.cookie('coupon_notify', 'yes', {expires:2}); //2天后重新通知
    layer.msg(msgstr, {
      time: 0, //不自动关闭
      icon: 1,
      title: titlestr ,
      offset:'100px',
      btn: [btnStr],
      skin: 'layui-layer-rim',
      shade: [0.5, '#393D49']
    });
  }

  var layid  = 0;
  var tipstr = '';
  function showMsg() {
    var info = "<span style='font-size:14px;font-family:Microsoft Yahei;'>" + tipstr+"</span>";
    layid = layer.tips(info, '#pay_info_tips', {
      tips: [1, '#3595CC'],
      time: 30000,
      area: '400px'
    });
  }
  $.post(Think.U('Home/User/getPayInfoStr'), {}, function(data,status){tipstr=data;});
  $("#pay_info_tips").hover(function(){showMsg();}, function(){layer.close(layid);});
  $("#pay_info_tips").click(function(){showMsg();});
  $("#pay_info_tips").blur(function(){layer.close(layid);});
}

function initLoginMsg(msg){
  var msgstr   = '<span style="font-size:14px;font-family:Microsoft Yahei;">' + msg + '</span>';
  var titlestr = '<span style="font-size:14px;font-family:Microsoft Yahei;">来自金雕的温馨提示:</span>';
  var btnStr   = '<span style="font-size:14px;font-family:Microsoft Yahei;">知道了</span>';
  var notified = $.cookie('assess_notify');
  if (notified != 'yes') {
    $.cookie('assess_notify', 'yes', {expires:1}); //1天后重新通知
    layer.msg(msgstr, {
      time: 0, //不自动关闭
      icon: 1,
      title: titlestr ,
      offset:'100px',
      btn: [btnStr],
      skin: 'layui-layer-rim',
      shade: [0.5, '#393D49']
    });
  }
}

function styleDOM(){
  /* styling nav status */
   $("ul.nav.nav-pills.nav-justified li:eq(0)").addClass("active");

  /* styling select options */
  $( "#Time" ).selectmenu({
    change: function(event, ui) {
      /* 移动端 */
      if ($("#mbl").val() == 1){
        if ($(this).val() == 0){
          $("#user_define_qry").css({
            "display":"inline"
            /* append more style here with format as json */
          });
        } else {
          $("#user_define_qry").css({
            "display":"none"
          });
        }
      } else {
        /* PC端 */
        if($(this).val() == 0){
          $("#recent_records").removeClass("col-md-8 col-md-offset-2");
          $("#recent_records").addClass("col-md-10 col-md-offset-1");
          $("#recent_records_qry").css({
            "margin-left":"15%"
          });
          $("#user_define_qry").css({
            "display":"inline"
            /* append more style here with format as json */
          });
        }else{
          $("#recent_records").removeClass("col-md-10 col-md-offset-1");
          $("#recent_records").addClass("col-md-8 col-md-offset-2");
          $("#recent_records_qry").css({
            "margin-left":"20%"
          });
          $("#user_define_qry").css({
            "display":"none"
          });
        }
      }
    },
    width:120
  });

  $("#StrategyType").selectmenu({
    width:150
  }).selectmenu( "menuWidget" ).addClass( "overflow" );

  $("#StrategyType").selectmenu({
    width:150
  }).selectmenu( "menuWidget" ).addClass( "overflow" );
}

$(document).ready(function(){

  $.post(Think.U('Home/User/getUserInfo'),{},function(data,textStatus){
	  var json = PPD.toJson(data);
		if(json.status==0){
			$("#balance").html(json.balance);
			$("#expire_date").html(json.expire_date);
			$("#enable_sys_strategy_count").html(json.enable_sys_strategy_count);
			$("#enable_diy_strategy_count").html(json.enable_diy_strategy_count);
			$("#total_bid_amount").html(json.total_bid_amount);
			$("#total_bid_count").html(json.total_bid_count);
			$("#total_bid_gain").html(json.total_bid_gain);
			$("#total_repay_count").html(json.total_repay_count);
			$("#total_delay_amount").html(json.total_delay_amount);
			$("#total_delay_ratio").html(json.total_delay_ratio);

			$("#tdy_bid_amount").html(json.tdy_bid_amount);
			$("#tdy_bid_count").html(json.tdy_bid_count);
			$("#yes_bid_amount").html(json.yes_bid_amount);
			$("#yes_bid_count").html(json.yes_bid_count);
		}else{
			alert("网络错误，请刷新重试...");
		}
  });

  $.post(Think.U('Home/User/getEnabledStrategyNameList'),{},function(data,textStatus){
    var json = PPD.toJson(data);
    if(json['status']==0){
      var id;
      var str='<option value="-1">所有</option>\n';
      str = str + '<option value="-2">所有系统</option>\n';
      str = str + '<option value="-3">所有自定义</option>\n';
      for (id in json) {
        if(json[id]!=0)
          str=str+'<option value="' + id  + '">' + json[id] + '</option>\n';
      }
      $("#StrategyType").html(str);
    }else if (json['status'] == 1){
    	//may be check user status and give some tips or help guide!
    }else{
      alert("网络错误，请刷新重试...");
    }
  });

  $("#user_pay").click(function(){
	  var price = $("input[name='price']:checked").val();
	  var ptype=4;

		  if(price==null){
			  alert("请选择充值金额");
		  }else{
			  /* 判断推荐码是否合法，如不合法提示原因*/
			  var jdcode = $('#jdcode').val();
			  $.post(Think.U('Home/User/updateJdcode'),{jdcode:jdcode},function(data,textStatus){
				  if("OK" != data){
					  alert(data);
				  }
			  })
			  var random=Math.random();
			  random=Math.floor(random*99);
			  price=price*3700+random;
			  location.href = ROOT+"/home/wxpay/index/prc/" + price + "/ptype/"+ ptype +".html";
		  }
  });

  $("#myjdcode").click(function(){
	  location.href = ROOT + "/home/user/promotion.html";
  });

  /* page DOM styling */
  styleDOM();

  /* tips layer for coupon
  initCouponTipLayer();
   */

  /* query table, list basic bid info */
  var table = $('#tblBidlist');
  initQueryTable(table);
  $("#qrybid").click(function () {
    table.fnDestroy(false);
    initQueryTable(table)
    table.fnPageChange(0);
  });

  $("#downloadBid").click(function(){
    location.href = ROOT+"/home/user/downloadBidData";
  });

  /* init datepicker */
  var config = getDPConfig( {} );
  $.datepicker.setDefaults(config);
  $("#date_start").datepicker($.datepicker);
  $("#date_end").datepicker($.datepicker);

});
