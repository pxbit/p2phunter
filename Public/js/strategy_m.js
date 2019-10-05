function updateStrategySetting(elem, val, mode)
{
  var status = false;
  var params = new Object();
  var strategy_id = elem.id;
  params.strategy_id = strategy_id;
  params.val = val;
  params.switch = (mode == 'open')? 1 : 0;

  $.post(Think.U('Home/Strategy/updateUserStrategySetting', params), {}, function(data,textStatus){
    var json = PPD.toJson(data);
    if( json.status == 0){
      status = true;
      $.post(Think.U('Home/Strategy/ajaxGetStrategyAppliedInfo', {}),
        {}, function(data, textStatus){
          var json = PPD.toJson(data);
          var str ="已开启 " + json.cntOn + " 个，共 " + json.cntTotal +" 个";
          $("#strategy-overiew").html(str);
        });
    }
    else if(json.status < 0){
      alert(json.ErrMsg);
    }else{
      if(mode == 'open')
        alert('网络错误，策略开启失败，请刷新后重试');
      else if(mode == 'close')
        alert('网络错误，策略关闭失败，请刷新后重试');
      else if(mode =='change')
        alert('网络错误，请刷新后重试');
    }
  });
}


function decideBidAmountByRate(rate){
	if(rate == 12.5)
		return 50;
	else if(rate == 13)
		return 61;
	else if(rate == 15)
		return 73;
	else if(rate == 18)
		return 67;
	else if(rate == 20)
		return 66;
	else
		return 51;
}


function swapSwitch(obj){
  var elemId = obj.id;

  /* 获取最近的dt节点的兄弟节点 */
  var o = $(obj).closest("dt").siblings();

  /* bidAmount为最后一个节点*/
  var objAmnt = o[o.length - 1];
  var val = $(objAmnt).attr('value');

  if ($(obj).prop("checked")){
    updateStrategySetting(obj, val, 'open');
    $(obj).next().html("开");
  } else {
    updateStrategySetting(obj, val, 'close');
    $(obj).next().html("关");
  }
}


function forceSwitch(obj, type){
  var elemId = obj.id;

  /* 获取最近的dt节点的兄弟节点 */
  var o = $(obj).closest("dt").siblings();

  /* bidAmount为最后一个节点*/
  var objAmnt = o[o.length - 1];
  var val = $(objAmnt).attr('value');

  if (1 == type){
    updateStrategySetting(obj, val, 'open');
    $(obj).next().html("开");
  } else {
    updateStrategySetting(obj, val, 'close');
    $(obj).next().html("关");
  }
}


function switchAll(type){
  var ar = $("input:checkbox[name='strategy-switch']");
  var len = ar.length;

  for (var i = 0; i < len; i++){
    forceSwitch(ar[i], (type == 'open')? 1 : 0);
    $(ar[i]).prop("checked", (type == 'open')? true : false );
  }

  if (type == 'open'){
    $("#switch-all-close").show();
    $("#switch-all-open").hide();
  } else {
    $("#switch-all-open").show();
    $("#switch-all-close").hide();
  }

}


function triggerClick(type) {
  var cnt = (type == 'open')?"确认开启所有策略?" : "确认关闭所有策略?";
  $.confirm({
    title: '确认',
    content: cnt,
    type: 'orange',
    icon: 'glyphicon glyphicon-question-sign',
    buttons: {
      ok: {
        text: '确认',
        btnClass: 'btn-primary',
        action: function() {
          switchAll(type);
        }
      },
      cancel: {
        text: '取消',
        btnClass: 'btn-primary'
      }
    }
  });
}


$(document).ready(function(){
  $("#switch-all-open").click(function(){
    triggerClick('open');
  });

  $("#switch-all-close").click(function(){
    triggerClick('close');
  });

});


