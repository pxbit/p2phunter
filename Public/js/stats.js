

function getStrategyList(bid_amount_list)
{
	 $.post(Think.U('Home/Strategy/getSysStrategyStats'),{},function(data,textStatus){
		  var json = PPD.toJson(data);
		  if( json['status']==0){
				var str="<tbody><tr><th align=\"left\">策略名称</th><th>平均投资利率</th><th>历史坏标率</th><th>预期年化收益率</th><th>七日出标量</th><th>单次投标金额</th><th>状态</th></tr>";
				for (id in json['unilist']){
					var bid_amount=0;
					var strategy=json['unilist'][id];
					{
						for(index in bid_amount_list){
							if(bid_amount_list[index].StrategyId==strategy.StrategyId){
								bid_amount=bid_amount_list[index].BidAmount;
							}
						}
						if(bid_amount>=50)
							var strategy_switch='<div class ="switch open" >开</div>';
						else 
							var strategy_switch='<div class ="switch close" >关</div>';
						if(strategy.StrategyId<10)
							$lable="<font color=\"#00d040\">[赔]</font>";
						else $lable="<font color=\"#2040ff\">[信]</font>";
						str=str + "<tr strategy_id='" + strategy.StrategyId + "'><td align=\"left\" title='"
							+ strategy.Discription + "'>" + $lable + "<font color=\"#309000\">" +  strategy.Name
							+ "</font></td><td class=\"bidrate\" rate=\"" + strategy.BidRate + "\" >"
							+ strategy.BidRate + "%</td><td>" + ((strategy.DelayRate<0)?'-': (strategy.DelayRate +'%'))
							+ "</td><td>"+ ((strategy.ExpectRate<0)?'-':(strategy.ExpectRate+'%')) + "</td><td>"
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
						if(strategy.BidAmount>=50)
							var strategy_switch='<div class ="switch open" >开</div>';
						else 
							var strategy_switch='<div class ="switch close" >关</div>';
							rate='-';
						str=str + "<tr strategy_id='" + strategy.StrategyId + "'><td align=\"left\" title=\""
							+ strategy.Description + "\" class=\"strategy_title\">" + $lable + "<font color=\"#309000\">" +  strategy.StrategyName
							+ "</font></td><td class=\"bidrate\" rate=\"" + rate + "\" >"
							+ rate + "%</td><td>" + '-' + "</td><td>"+ '-' + "</td><td>"
							+ '-' + "</td>";
						str=str + "<td><input type='text' alt ='50~500' readonly='readonly' class='set_bid_amount' value='"
							+ strategy.BidAmount +"' /></td><td>"+ strategy_switch + "</td></tr>";
				
					}

				}
				$("#strategy_setting_table").html(str);
		  }else{
				alert("网络错误，获取策略列表失败，请刷新后再试！");
		  }
		  
	});
}
function getSysStrategyStats()
{
	 $.post(Think.U('Home/Strategy/getSysStrategyStats'),{},function(data,textStatus){
		  var json = PPD.toJson(data);
		  if( json['status']==0){
				var str="<tbody><tr><th align=\"left\">策略名称</th><th>平均投资期限</th><th>平均投资利率</th><th>30日逾期率</th>"
					+"<th>90日逾期率</th><th>1期30日逾期</th><th>2期30日逾期</th><th>3期30日逾期</th></tr>";
				var fd30=0,fa30=0;
				var sd30=0,sa30=0;
				var td30=0,ta30=0;
				for(var id in json['stats']){
					var stgy=json['stats'][id];
					str=str + "<tr strategy_id='" + stgy.StrategyId + "'>" 
					+ "<td align=\"left\" >"  +  stgy.Name + "</td>" 
					+ "<td >" + stgy.AVGM + "</td>" 
					+ "<td class=\"bidrate\" >"  + (stgy.AVGR*100).toFixed(2) + "%</td>"
					+ "<td>"+ ((stgy.D30 < 0)?'-':((stgy.D30*100).toFixed(2) +'%')) + "</td>"
					+ "<td>" + ((stgy.D90 < 0)?'-':((stgy.D90*100).toFixed(2) +'%')) + "</td>"
					+ "<td>" + stgy.FD30 + "/" + stgy.FA30 + "</td>"
					+ "<td>" + stgy.SD30 + "/" + stgy.SA30 + "</td>"
					+ "<td>" + stgy.TD30 + "/" + stgy.TA30 + "</td>"
					+ "</tr>";
					fd30+=parseInt(stgy.FD30);
					fa30+=parseInt(stgy.FA30);
					sd30+=parseInt(stgy.SD30);
					sa30+=parseInt(stgy.SA30);
					td30+=parseInt(stgy.TD30);
					ta30+=parseInt(stgy.TA30);
				}
				str=str + "<tr strategy_id='" + 0 + "'>" 
				+ "<td align=\"left\" >"  +  "汇总" + "</td>"
				+ "<td >" + "-" + "</td>" 
				+ "<td class=\"bidrate\" > - </td>" 
				+ "<td> - </td>"
				+ "<td> - </td>"
				+ "<td>" + fd30 + "/" + fa30 + "</td>"
				+ "<td>" + sd30 + "/" + sa30 + "</td>"
				+ "<td>" + td30 + "/" + ta30 + "</td>"
				+ "</tr>";
				var d = new Date()
				hours = Math.round((d.getTime()/1000 - json['time'])/60/60)
				str = str + "<tr><td align = \"left\"></td><td></td><td></td><td></td><td></td><td>最后更新时间</td><td>" + hours +" 小时 前</td></tr>";
				$("#strategy_setting_table").html(str);
		  }else{
			  alert("网络错误，请刷新后再试！");
		  }
	 });
				

}

$(document).ready(function(){
	var root = "__ROOT__";
	$("#strategy_diy_save").click( function(){
		location.href = ROOT+"/home/strategy.html";
	});
	getSysStrategyStats();

});
