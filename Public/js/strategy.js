function switchOn(elem,amount)
{
	$(elem).parent().parent().children().children('.set_bid_amount').val(amount);
	$(elem).removeClass("close");
	$(elem).addClass("open");
	$(elem).html('开');
}

function switchOff(elem)
{
	//$(elem).parent().parent().children().children('.set_bid_amount').val('0');
	$(elem).removeClass("open");
	$(elem).addClass("close");
	$(elem).html('关');
}
function updateStrategySettingById(strategy_id,val)
{
	var status=false;
	var params=new Object();
	params.strategy_id=strategy_id;
	params.val=val;
	$.post(Think.U('Home/Strategy/updateUserStrategySetting',params),{},function(data,textStatus){
			  var json = PPD.toJson(data);
				if( json.status==0){
					status=true;
				}
				else if(json.status<0){
					alert(json.ErrMsg);
				}else{
						alert('网络错误，请刷新后重试');
				}
	});
}
function updateStrategySetting(elem,val,mode)
{
    var status=false;
    var params=new Object();
    var strategy_id=$(elem).parent().parent().attr('strategy_id');
    params.strategy_id=strategy_id;
    params.val=val;
    params.switch = (mode == 'close')? 0 : 1;

    $.post(Think.U('Home/Strategy/updateUserStrategySetting',params),{},function(data,textStatus){
        var json = PPD.toJson(data);
        if( json.status==0){
            status=true;
            if(mode=='open')
                switchOn(elem,val);
            else if(mode=='close')
                switchOff(elem);	
        }
        else if(json.status<0){
            alert(json.ErrMsg);
        }else{
            if(mode=='open')
                alert('网络错误，策略开启失败，请刷新后重试');
            else if(mode=='close')
                alert('网络错误，策略关闭失败，请刷新后重试');
            else if(mode=='change')
                alert('网络错误，请刷新后重试');
        }
    });
}

function decideBidAmountByRate(rate){
	if(rate==12.5)
		return 50;
	else if(rate==13)
		return 61;
	else if(rate==15)
		return 73;
	else if(rate==18)
		return 67;
	else if(rate==20)
		return 66;
	else
		return 51;
}

function isInteger(x) {return (typeof x === 'number') && (x % 1 === 0);}

function delayRegisterAction()
{
	  //注册动作
		$("#strategy_setting_table").on("click",".switch.close", function() {
			var str=$(this).parent().parent().children('.bidrate').attr('rate');
			var amount = $(this).parent().parent().children().children('.set_bid_amount').val()
			amount = Math.max(amount,50);
			updateStrategySetting(this, amount,'open');
		 });
		$("#strategy_setting_table").on("click",".strategy_title", function() {
			var strategy_id=$(this).parent().attr('strategy_id');
			location.href = ROOT+"/home/strategy/diy/stid/"+ strategy_id + ".html";
		 });
		$("#strategy_setting_table").on("click",".switch.open", function() {
			var amount = $(this).parent().parent().children().children('.set_bid_amount').val()
			 updateStrategySetting(this, amount,'close');
		 });
		$("#strategy_setting_table").on("click","input", function() {
		     //do something here
			if($(this).parent().parent().children().children('.switch').hasClass('close'))
			{
				alert("请先开启策略，再设置投标金额");
			}else{
				$(this).removeClass('set_bid_amount');
				$(this).addClass('change_bid_amount');
				$(this).removeAttr('readonly');
			}
		 });
		
		$("#strategy_setting_table").on("focusout","input", function() {
			if($(this).parent().parent().children().children('.switch').hasClass('open'))
			{
				var amount=Number($(this).val());
				if(amount<50){
					$(this).val(50);
					alert('投标额必须在50~500之间，您输入的值太小，已为您设置最小投标金额50。');
					
				}else if(amount>500){
					$(this).val(500);
					alert('投标额必须在50~500之间，您输入的值太大，已为您设置为最大投标金额500。');
				}
				
				$(this).removeClass('change_bid_amount');
				$(this).addClass('set_bid_amount');
				
				if($(this).val()>=50 && $(this).val()<=500){
					updateStrategySetting(this,$(this).val(),'update');
				}else
				{
					alert('投标额必须是50~500之间的整数值，请刷新后重新设置');
				}
				
			}
		 });
		
		$("#strategy_all_open").click( function(){
			if( confirm("您确认要全部打开策略吗？"))
				$(".switch.close").click();
		});
		
		$("#strategy_all_close").click( function(){
			if( confirm("您确认要全部关闭策略吗？关闭后系统将不再投资."))
				$(".switch.open").click();
		});
		$("#strategy_suggest_open").click( function(){
			location.href = ROOT+"/home/strategy/stats.html";
		});
		$("#save_global_setting").click( function(){
			var params=new Object();
			params.minRate = $("#MinRate").val() == "" ? 0 : $("#MinRate").val();
			params.minBalance = $("#MinBalance").val() == "" ? 0 : $("#MinBalance").val();
			params.minMonth = $("#MinMonth").val() == "" ? 0 : $("#MinMonth").val();
			params.maxMonth = $("#MaxMonth").val() == "" ? 36 : $("#MaxMonth").val();
			if (!(params.minRate >= 0 && params.minRate <= 36))
				alert("利率设置需要在0-36之间");
			else if(!(params.minBalance % 1 === 0) || params.minBalance < 0)
				alert("保留金额必须为0或者正整数" + params.minBalance);
			else if(!(params.minMonth % 1 === 0) || !(params.maxMonth % 1 === 0) || Number(params.minMonth) > Number(params.maxMonth) || params.minMonth<0 || params.maxMonth>36)
				if (Number(params.minMonth) > Number(params.maxMonth))
					alert("起投月份不能大于最长月份:" + params.minMonth + ">" + params.maxMonth);
				else
					alert("投标月份设置不正确！ 请输入0-36的整数！")
			else{
				$.post(Think.U('Home/Strategy/updateUserGlobalSetting',params),{},function(data,textStatus){
					  var json = PPD.toJson(data);
					  if (json.status==0){
						  status=true;
						  alert("保存成功，10分钟后生效。" + json.data);
					  }else if (json.status<0){
						  alert(json.ErrMsg);
					  }else{
						  alert('网络错误，请刷新后重试');
					  }
				});
			}
		});



}

function getStrategyList(bid_amount_list)
{
	 $.post(Think.U('Home/Strategy/getStrategyList'),{},function(data,textStatus){
		  var json = PPD.toJson(data);
		  if( json['status']==0){
				var str="<tbody><tr><th align=\"left\">策略名称</th><th>平均投资利率</th><th>预期年化收益率</th><th>七日投标量</th><th>单次投标金额</th><th>状态</th></tr>";
				for (id in json['unilist']){
					var bid_amount=0;
					var apply_status = 0;
					var strategy=json['unilist'][id];
					{
						for(index in bid_amount_list){
							if(bid_amount_list[index].StrategyId==strategy.StrategyId){
								bid_amount=bid_amount_list[index].BidAmount;
								apply_status = bid_amount_list[index].ApplyStatus;
							}
						}
						if(apply_status > 0)
							var strategy_switch='<div class ="switch open" >开</div>';
						else 
							var strategy_switch='<div class ="switch close" >关</div>';
						if(strategy.StrategyId<10 || (strategy.StrategyId>=1000 && strategy.StrategyId<2000))
							$lable="<font color=\"#00d040\">[赔]</font>";
						else $lable="<font color=\"#2040ff\">[信]</font>";
						str=str + "<tr strategy_id='" + strategy.StrategyId + "'><td align=\"left\" title='"
							+ strategy.Discription + "'>" + $lable + "<font color=\"#309000\">" +  strategy.Name
							+ "</font></td><td class=\"bidrate\" rate=\"" + strategy.BidRate + "\" >"
							+ strategy.BidRate + "%</td><td>"+ ((strategy.ExpectRate<0)?'-':(strategy.ExpectRate+'%')) + "</td><td>"
							+ (strategy.K7>0?strategy.K7:'-') + "</td>";
						str=str + "<td><input type='text' alt ='50~500' readonly='readonly' class='set_bid_amount' value='"
							+bid_amount+"' /></td><td>"+ strategy_switch + "</td></tr>";
					}
				}
				if(json['diy']['status']==0){	
					$lable="<font color=\"#ff00f0\">[自]</font>";
					for(id in json['diy']['diylist']){
						var strategy=json['diy']['diylist'][id];
						var rate=0;
						if(strategy.ApplyStatus > 0)
							var strategy_switch='<div class ="switch open" >开</div>';
						else 
							var strategy_switch='<div class ="switch close" >关</div>';
							rate='-';
						str=str + "<tr strategy_id='" + strategy.StrategyId + "'><td align=\"left\" title=\""
							+ strategy.Description + "\" class=\"strategy_title\">" + $lable + "<font color=\"#309000\">" +  strategy.StrategyName
							+ "</font></td><td class=\"bidrate\" rate=\"" + rate + "\" >"
							+ rate + "%</td><td>" + '-' + "</td><td>"
							+ '-' + "</td>";
						str=str + "<td><input type='text' alt ='50~500' readonly='readonly' class='set_bid_amount' value='"
							+ strategy.BidAmount +"' /></td><td>"+ strategy_switch + "</td></tr>";
				
					}
				}
				$("#strategy_setting_table").html(str);
				delayRegisterAction();
		  }else{
				alert("网络错误，获取策略列表失败，请刷新后再试！");
		  }
		  
	});
}
$(document).ready(function(){
	var root = "__ROOT__";

	$("#diy_new_strategy").click( function(){
		location.href = ROOT+"/home/strategy/diy/stid/0.html";
	});
	$("#strategy_stats").click( function(){
		location.href = ROOT+"/home/strategy/stats.html";
	});
	
	
	var bid_amount_list= new Array();
	$.post(Think.U('Home/Strategy/getUserStrategySetting'),{},function(data,textStatus){
		var json = PPD.toJson(data);
			if( json['status']==0){
						bid_amount_list=json;
			}else{
				//alert("fail getUserStrategySetting");
			}
		getStrategyList(bid_amount_list);
		  
	 });
	  
	 $.post(Think.U('Home/Strategy/getUserGlobalSetting'),{},function(data,textStatus){
		var json = PPD.toJson(data);
		if( json['status']==0){
			$("#MinRate").val(json['MinRate']);
			$("#MinMonth").val(json['MinMonth']);
			$("#MaxMonth").val(json['MaxMonth']);
			$("#MinBalance").val(json['MinBalance']);
		}else{
				//alert("fail getUserStrategySetting");
		}
	 });
	  

  

});
