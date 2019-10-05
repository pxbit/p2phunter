var ds=new Object();;
function CreateDefaultSetting()
{
	ds.StrategyId=0;
	ds.UserId=0;
	ds.StrategyLevel=0;
	ds.StrategyName="我的策略名称";
	ds.Description="我的策略描述";
	ds.AmountA="";
	ds.AmountB="";
	ds.MonthA="";
	ds.MonthB="";
	ds.RateA="";
	ds.RateB="";
	ds.Credit=126;
	ds.CertificateValidate=0;
	ds.CreditValidate=0;
	ds.NciicIdentityCheck=0;
	ds.PhoneValidate=0;
	ds.CertificateValidateFalse=0;
	ds.CreditValidateFalse=0;
	ds.NciicIdentityCheckFalse=0;
	ds.PhoneValidateFalse=0;
	ds.Degree=63;
	ds.StudyStyle=63;
	ds.School=15;
	ds.Gender=3;
	ds.Age=31;
	ds.AgeRangeA = "";
	ds.AgeRangeB = "";
	ds.AvgBorrowIntervalA = "";
	ds.AvgBorrowIntervalB = "";
	ds.LastSuccessIntervalA = "";
	ds.LastSuccessIntervalB = "";
	ds.CurAvgIntervalRatioA = "";
	ds.CurAvgIntervalRatioB = "";
	ds.FirstSuccessIntervalA = "";
	ds.FirstSuccessIntervalB = "";
	ds.RegisterFirstIntervalA = "";
	ds.RegisterFirstIntervalB = "";
	ds.RegisterMonthA = "";
	ds.RegisterMonthB = "";
	ds.SuccessCountA = "";
	ds.SuccessCountB = "";
	ds.WasteCountA = "";
	ds.WasteCountB = "";
	ds.NormalCountA = "";
	ds.NormalCountB = "";
	ds.NormalSuccessRatioA = "";
	ds.NormalSuccessRatioB = "";
	ds.DelayNormalRatioA = "";
	ds.DelayNormalRatioB = "";
	ds.OverdueCountA = "";
	ds.OverdueCountB = "";
	ds.OverdueMoreCountA = "";
	ds.OverdueMoreCountB = "";
	ds.OwingAmountA = "";
	ds.OwingAmountB = "";
	ds.OwingAfterAmountA = "";
	ds.OwingAfterAmountB = "";
	ds.OwingPrevHighDebtRatioA = "";
	ds.OwingPrevHighDebtRatioB = "";
	ds.OwingHighDebtRatioA = "";
	ds.OwingHighDebtRatioB = "";
	ds.LastHighestBorrowRatioA = "";
	ds.LastHighestBorrowRatioB = "";
	ds.TotalBorrowA = "";
	ds.TotalBorrowB = "";
	ds.OwnPreTotalBorrowRatioA = "";
	ds.OwnPreTotalBorrowRatioB = "";
	ds.CurAmountTotalBorrowRatioA = "";
	ds.CurAmountTotalBorrowRatioB = "";
	ds.AvgBorrowAmountA = "";
	ds.AvgBorrowAmountB = "";
	ds.CurAmountAvgBorrowRatioA = "";
	ds.CurAmountAvgBorrowRatioB = "";
	ds.BidAmount="50";
	ds.DayAmountLimit="0";
	/*new*/
	ds.TailNumber10 = 0;
	ds.HighDebtA = "";
	ds.HighDebtB = "";
	ds.OwingAmountRatioA = "";
	ds.OwingAmountRatioB = "";
	ds.WasteNormalRatioA ="";
	ds.WasteNormalRatioB = "";
	ds.CancelCountA = "";
	ds.CancelCountB = "";
	ds.FailCountA = "";
	ds.FailCountB = "";
	return ds;
}
function showSelectStatus(idPrefix,mask,maxValue){
	for(var i=1;i<=maxValue;i<<=1)
	{
		var id="#"+idPrefix;
		if(i<10)
			id=id+'0';
		id=id+i;
		if(mask&i){
			if($(id).hasClass('unselect')){
				$(id).removeClass("unselect");
				$(id).addClass("select");
			}
		}else{
			if($(id).hasClass('select')){
				$(id).removeClass("select");
				$(id).addClass("unselect");
			}
		}
			
	}
}
function showValidateSelectStatus(name,selected)
{	
	selected=parseInt(selected);
	if(selected && $("#"+name).hasClass('unselect')){
		$("#"+name).removeClass("unselect");
		$("#"+name).addClass("select");
	}else if(!selected && $("#"+name).hasClass('select')){
		$("#"+name).removeClass("select");
		$("#"+name).addClass("unselect");
	}
}
function showSetting(setting){
	$("#strategynameA").val(setting.StrategyName);
	$("#strategydiscA").val(setting.Description);
	$("#AmountA").val(setting.AmountA);
	$("#AmountB").val(setting.AmountB);
	$("#MonthA").val(setting.MonthA);
	$("#MonthB").val(setting.MonthB);
	$("#RateA").val(setting.RateA);
	$("#RateB").val(setting.RateB);
	$("#AgeRangeA").val(setting.AgeRangeA);
	$("#AgeRangeB").val(setting.AgeRangeB);
	$("#AvgBorrowIntervalA").val(setting.AvgBorrowIntervalA);
	$("#AvgBorrowIntervalB").val(setting.AvgBorrowIntervalB);
	$("#LastSuccessIntervalA").val(setting.LastSuccessIntervalA);
	$("#LastSuccessIntervalB").val(setting.LastSuccessIntervalB);
	$("#CurAvgIntervalRatioA").val(setting.CurAvgIntervalRatioA);
	$("#CurAvgIntervalRatioB").val(setting.CurAvgIntervalRatioB);
	$("#FirstSuccessIntervalA").val(setting.FirstSuccessIntervalA);
	$("#FirstSuccessIntervalB").val(setting.FirstSuccessIntervalB);
	$("#RegisterFirstIntervalA").val(setting.RegisterFirstIntervalA);
	$("#RegisterFirstIntervalB").val(setting.RegisterFirstIntervalB);
	$("#RegisterMonthA").val(setting.RegisterMonthA);
	$("#RegisterMonthB").val(setting.RegisterMonthB);
	$("#SuccessCountA").val(setting.SuccessCountA);
	$("#SuccessCountB").val(setting.SuccessCountB);
	$("#WasteCountA").val(setting.WasteCountA);
	$("#WasteCountB").val(setting.WasteCountB);
	$("#NormalCountA").val(setting.NormalCountA);
	$("#NormalCountB").val(setting.NormalCountB);
	$("#NormalSuccessRatioA").val(setting.NormalSuccessRatioA);
	$("#NormalSuccessRatioB").val(setting.NormalSuccessRatioB);
	$("#DelayNormalRatioA").val(setting.DelayNormalRatioA);
	$("#DelayNormalRatioB").val(setting.DelayNormalRatioB);
	$("#OverdueCountA").val(setting.OverdueCountA);
	$("#OverdueCountB").val(setting.OverdueCountB);
	$("#OverdueMoreCountA").val(setting.OverdueMoreCountA);
	$("#OverdueMoreCountB").val(setting.OverdueMoreCountB);
	$("#OwingAmountA").val(setting.OwingAmountA);
	$("#OwingAmountB").val(setting.OwingAmountB);
	$("#OwingAfterAmountA").val(setting.OwingAfterAmountA);
	$("#OwingAfterAmountB").val(setting.OwingAfterAmountB);
	$("#OwingPrevHighDebtRatioA").val(setting.OwingPrevHighDebtRatioA);
	$("#OwingPrevHighDebtRatioB").val(setting.OwingPrevHighDebtRatioB);
	$("#OwingHighDebtRatioA").val(setting.OwingHighDebtRatioA);
	$("#OwingHighDebtRatioB").val(setting.OwingHighDebtRatioB);
	$("#LastHighestBorrowRatioA").val(setting.LastHighestBorrowRatioA);
	$("#LastHighestBorrowRatioB").val(setting.LastHighestBorrowRatioB);
	$("#TotalBorrowA").val(setting.TotalBorrowA);
	$("#TotalBorrowB").val(setting.TotalBorrowB);
	$("#OwnPreTotalBorrowRatioA").val(setting.OwnPreTotalBorrowRatioA);
	$("#OwnPreTotalBorrowRatioB").val(setting.OwnPreTotalBorrowRatioB);
	$("#CurAmountTotalBorrowRatioA").val(setting.CurAmountTotalBorrowRatioA);
	$("#CurAmountTotalBorrowRatioB").val(setting.CurAmountTotalBorrowRatioB);
	$("#AvgBorrowAmountA").val(setting.AvgBorrowAmountA);
	$("#AvgBorrowAmountB").val(setting.AvgBorrowAmountB);
	$("#CurAmountAvgBorrowRatioA").val(setting.CurAmountAvgBorrowRatioA);
	$("#CurAmountAvgBorrowRatioB").val(setting.CurAmountAvgBorrowRatioB);
	$("#BidAmountA").val(setting.BidAmount);
	$("#DayAmountLimitA").val(setting.DayAmountLimit);
	$("#HighDebtA").val(setting.HighDebtA);
	$("#HighDebtB").val(setting.HighDebtB);
	$("#OwingAmountRatioA").val(setting.OwingAmountRatioA);
	$("#OwingAmountRatioB").val(setting.OwingAmountRatioB);
	$("#WasteNormalRatioA").val(setting.WasteNormalRatioA);
	$("#WasteNormalRatioB").val(setting.WasteNormalRatioB);
	$("#CancelCountA").val(setting.CancelCountA);
	$("#CancelCountB").val(setting.CancelCountB);
	$("#FailCountA").val(setting.FailCountA);
	$("#FailCountB").val(setting.FailCountB);
	showSelectStatus("Credit",setting.Credit,64);
	showSelectStatus("Degree",setting.Degree,32);
	showSelectStatus("StudyStyle",setting.StudyStyle,32);
	showSelectStatus("School",setting.School,8);
	showSelectStatus("Gender",setting.Gender,2);
	showSelectStatus("Age",setting.Age,16);
	showValidateSelectStatus("CertificateValidate",setting.CertificateValidate);
	showValidateSelectStatus("CreditValidate",setting.CreditValidate);
	showValidateSelectStatus("PhoneValidate",setting.PhoneValidate);
	showValidateSelectStatus("NciicIdentityCheck",setting.NciicIdentityCheck);
	showValidateSelectStatus("CertificateValidateFalse",setting.CertificateValidateFalse);
	showValidateSelectStatus("CreditValidateFalse",setting.CreditValidateFalse);
	showValidateSelectStatus("PhoneValidateFalse",setting.PhoneValidateFalse);
	showValidateSelectStatus("NciicIdentityCheckFalse",setting.NciicIdentityCheckFalse);
	showValidateSelectStatus("TailNumber10",setting.TailNumber10);

}

function switchValidationStatus(elem){
	if($(elem).hasClass('select'))
	{
		$(elem).removeClass("select");
		$(elem).addClass("unselect");
		return 0;
		
	}else{
		$(elem).removeClass("unselect");
		$(elem).addClass("select");
		return 1;
	}
}

function switchSelectionStatus(elem,oldValue,id,msg)
{
	var newValue;
	if($(elem).hasClass('select')){
		newValue=oldValue&(~id);
		$(elem).removeClass("select");
		$(elem).addClass("unselect");
		if(newValue==0){
			alert(msg);
		}
	}else{
		$(elem).removeClass("unselect");
		$(elem).addClass("select");
		newValue=oldValue|id;
	}
	return newValue;
}
function delayRegisterAction()
{
	  //注册多项选择响应器
	$("#Credit").on("click",".OptionLittle", function() {
		var id=$(this).attr('id').substr(6,2);
		ds.Credit=switchSelectionStatus(this,ds.Credit,id,"你没有选择任何信用等级，将无法为您投标！");
	});
	$("#Degree").on("click",".OptionLittle", function() {
		var id=$(this).attr('id').substr(6,2);
		ds.Degree=switchSelectionStatus(this,ds.Degree,id,"你没有选择任何学历类型，将无法为您投标！");
	});
	$("#StudyStyle").on("click",".OptionMiddle", function() {
		var id=$(this).attr('id').substr(10,2);
		ds.StudyStyle=switchSelectionStatus(this,ds.StudyStyle,id,"你没有选择任何学习形式，将无法为您投标！");
	});
	$("#School").on("click",".OptionMiddle", function() {
		var id=$(this).attr('id').substr(6,2);
		ds.School=switchSelectionStatus(this,ds.School,id,"你没有选择任何学校类型，将无法为您投标！");
	});
	$("#Gender").on("click",".OptionMiddle", function() {
		var id=$(this).attr('id').substr(6,2);
		ds.Gender=switchSelectionStatus(this,ds.Gender,id,"你没有选择任何性别，将无法为您投标！");
	});
	$("#Age").on("click",".OptionMiddle", function() {
		var id=$(this).attr('id').substr(3,2);
		ds.Age=switchSelectionStatus(this,ds.Age,id,"你没有选择任何年龄段，将无法为您投标！");
	});
//注册认证选择响应器。
		$("#Validation").on("click","#CertificateValidate", function() {
			ds.CertificateValidate=switchValidationStatus(this);
		 });
		$("#Validation").on("click","#CreditValidate", function() {
			ds.CreditValidate=switchValidationStatus(this);
		});
		$("#Validation").on("click","#NciicIdentityCheck", function() {
			ds.NciicIdentityCheck=switchValidationStatus(this);
		});
		$("#Validation").on("click","#PhoneValidate", function() {
			ds.PhoneValidate=switchValidationStatus(this);
		});
		
		//注册认证排除响应器。
		$("#ValidationFalse").on("click","#CertificateValidateFalse", function() {
			ds.CertificateValidateFalse=switchValidationStatus(this);
		 });
		$("#ValidationFalse").on("click","#CreditValidateFalse", function() {
			ds.CreditValidateFalse=switchValidationStatus(this);
		});
		$("#ValidationFalse").on("click","#NciicIdentityCheckFalse", function() {
			ds.NciicIdentityCheckFalse=switchValidationStatus(this);
		});
		$("#ValidationFalse").on("click","#PhoneValidateFalse", function() {
			ds.PhoneValidateFalse=switchValidationStatus(this);
		});
		//注册金额排除响应器。
		$("#TailNumber").on("click","#TailNumber10", function() {
			ds.TailNumber10=switchValidationStatus(this);
		});

}
function checkStrategyName(){
	var pass=true;
	if($("#strategynameA").val()==""){
		$("#strategyname").children('.setting_tips').html("策略名称不能为空");
		$("#strategyname").children('.setting_tips').addClass('red');
		pass=false;
	}else if ($("#strategynameA").val().length>5){
		$("#strategyname").children('.setting_tips').html("策略名称最多5个字");
		$("#strategyname").children('.setting_tips').addClass('red');
		pass=false;
	}else{
		ds.StrategyName=$("#strategynameA").val();
		$("#strategyname").children('.setting_tips').removeClass('red');
	}
	return pass;	
}

function checkStrategyDisc(){
	var pass=true;
	if($("#strategydiscA").val()==""){
		$("#strategydisc").children('.setting_tips').html("策略描述不能为空");
		$("#strategydisc").children('.setting_tips').addClass('red');
		pass=false;
	}else{
		ds.Description=$("#strategydiscA").val();
		$("#strategydisc").children('.setting_tips').removeClass('red');
	}
	return pass;
		
}
function rangeValueValidCheck(a,b,tips)
{	
	var pass=true;
	if(a=="" && b==""){
		tips.removeClass('red');
	}else if (a==""&&!isNaN(parseFloat(b)) || !isNaN(parseFloat(a))&&b==""){
		tips.removeClass('red');
	}else if(isNaN(parseFloat(a))||isNaN(parseFloat(b))||parseFloat(a)<0){
		tips.html("请在两个输入框内设置正确的数值！");
		tips.addClass('red');
		pass=false;
	}else if(parseFloat(a)>parseFloat(b)){
		tips.html("左侧值应该小于等于右侧值，请重新输入！");
		tips.addClass('red');
		pass=false;
	}else{
		tips.removeClass('red');
	}
	return pass;
}

function checkRange(field){
	var tips=$("#" + field).children('.setting_tips');
	var pass=rangeValueValidCheck($("#" + field + "A").val(),$("#" + field + "B").val(),tips);
	if(pass){
		ds[field + "A"]=$("#" + field + "A").val();
		ds[field + "B"]=$("#" + field  + "B").val();
	}
	return pass;
}



function checkBidAmount(){
	var pass=true;
	var tips=$("#BidAmount").children('.setting_tips');
	var value=$("#BidAmountA").val()
	if(isNaN(parseInt(value))||value<50||value>500){
		tips.html("请输入50-500之间的整数值。");
		tips.addClass('red');
		pass=false;
		
	}else{
		ds.BidAmount=value;
	}
	return pass;
}

function checkDayAmountLimit(){
	var pass=true;
	var tips=$("#DayAmountLimit").children('.setting_tips');
	var value=$("#DayAmountLimitA").val()
	if(isNaN(parseInt(value))||value<0){
		tips.html("请输入自然数值。");
		tips.addClass('red');
		pass=false;
	}else{
		ds.DayAmountLimit=value;
	}
	return pass;
}

function checkParam(setting){
	var pass=true;
	pass=pass&checkStrategyName();
	pass=pass&checkStrategyDisc();
	pass=pass&checkRange("Amount");
	pass=pass&checkRange("Month");
	pass=pass&checkRange("Rate");
	pass=pass&checkRange("AgeRange");
	pass=pass&checkRange("AvgBorrowInterval");
	pass=pass&checkRange("LastSuccessInterval");
	pass=pass&checkRange("CurAvgIntervalRatio");
	pass=pass&checkRange("FirstSuccessInterval");
	pass=pass&checkRange("RegisterFirstInterval");
	pass=pass&checkRange("RegisterMonth");
	pass=pass&checkRange("SuccessCount");
	pass=pass&checkRange("WasteCount");
	pass=pass&checkRange("NormalCount");
	pass=pass&checkRange("NormalSuccessRatio");
	pass=pass&checkRange("DelayNormalRatio");
	pass=pass&checkRange("OverdueCount");
	pass=pass&checkRange("OverdueMoreCount");
	pass=pass&checkRange("OwingAmount");
	pass=pass&checkRange("OwingAfterAmount");
	pass=pass&checkRange("OwingPrevHighDebtRatio");
	pass=pass&checkRange("OwingHighDebtRatio");
	pass=pass&checkRange("LastHighestBorrowRatio");
	pass=pass&checkRange("TotalBorrow");
	pass=pass&checkRange("OwnPreTotalBorrowRatio");
	pass=pass&checkRange("CurAmountTotalBorrowRatio");
	pass=pass&checkRange("AvgBorrowAmount");
	pass=pass&checkRange("CurAmountAvgBorrowRatio");
	
	pass=pass&checkRange("HighDebt");
	pass=pass&checkRange("OwingAmountRatio");
	pass=pass&checkRange("WasteNormalRatio");
	pass=pass&checkRange("CancelCount");
	pass=pass&checkRange("FailCount");

	pass=pass&checkBidAmount();
	pass=pass&checkDayAmountLimit();
	return pass;
	
}
function doSubmit(setting){
	$.post(Think.U('Home/Strategy/diySubmit'),setting,function(data,textStatus){
		var json = PPD.toJson(data);
			if( json['status']!=0){
						alert(json['message']);
			}else{
				alert("策略保存成功！");
			}
		location.href = ROOT+"/home/strategy.html";
		  
	});
	
}


function showTestResult(loans)
{
	var str="<tbody><tr><th>标号</th><th>年龄</th><th>金额</th><th>性别</th><th>魔镜</th><th>利率</th><th>征信</th><th>学历</th><th>学习形式</th><th>学位</th><th>待还 </th><th>上次借款</th><th>首借</th><th>注册</th><th>成功借款次数</th><th> 累计借款总额 </th><th>详情</th></tr>";
	for(id in loans){
		str=str + "<tr><td>" + loans[id].ListingId
		+ "</td><td>" + loans[id].Age
		+ "</td><td>" + loans[id].Amount
		+ "</td><td>" + loans[id].Gender
		+ "</td><td>" + loans[id].CreditCode 
		+ "</td><td>" + loans[id].CurrentRate 
		+ "</td><td>" + loans[id].CreditValidate 
		+ "</td><td>" + loans[id].CertificateValidate 
		+ "</td><td>" + loans[id].StudyStyle
		+ "</td><td>" + loans[id].EducationDegree
		+ "</td><td>" + loans[id].OwingAmount
		+ "</td><td>" + loans[id].LastSuccessBorrowTime
		+ "</td><td>" + loans[id].FirstSuccessBorrowTime
		+ "</td><td>" + loans[id].RegisterTime
		+ "</td><td>" + loans[id].SuccessCount
		+ "</td><td>" + loans[id].TotalPrincipal
		+ "</td><td><a href=\"http://invest.ppdai.com/loan/info?id=" 
		+ loans[id].ListingId + "\" target=\"_blank\" >" + "详情" + "</a></td></tr>";
	}
	str=str+"</tbody>";
	$("#strategy_test_result_table").html(str);
}
function doTestDiy(setting){
	alert("测试可能需要几秒钟，请耐心等待！测试结果会显示在本页下方。");
	$.post(Think.U('Home/Strategy/diyMatchTest'),setting,function(data,textStatus){
		var json = PPD.toJson(data);
			if( json['status']!=0){
						alert(json['message']);
						$("#strategy_test_result_table").html("");
						$("#strategy_test_result_summary").html("");
			}else{
				showTestResult(json['loans']);
				$("#strategy_test_result_summary").html(json['summary']);
			}

	});
	
}
function getDsFromServer(ds){
	$.post(Think.U('Home/Strategy/getdiyStrategySetting'),{},function(data,textStatus){
		var json = PPD.toJson(data);
		if(json['status']==0)
		{	
			ds.StrategyId=json['data'].StrategyId;
			ds.UserId=json['data'].UserId;
			ds.StrategyLevel=json['data'].StrategyLevel;
			ds.StrategyName=json['data'].StrategyName;
			ds.Description=json['data'].Description;
			ds.AmountA=json['data'].AmountA;
			ds.AmountB=json['data'].AmountB;
			ds.MonthA=json['data'].MonthA;
			ds.MonthB=json['data'].MonthB;
			ds.RateA=json['data'].RateA;
			ds.RateB=json['data'].RateB;
			ds.Credit=json['data'].Credit;
			ds.CertificateValidate=json['data'].CertificateValidate;
			ds.CreditValidate=json['data'].CreditValidate;
			ds.NciicIdentityCheck=json['data'].NciicIdentityCheck;
			ds.PhoneValidate=json['data'].PhoneValidate;
			ds.CertificateValidateFalse=json['data'].CertificateValidateFalse;
			ds.CreditValidateFalse=json['data'].CreditValidateFalse;
			ds.NciicIdentityCheckFalse=json['data'].NciicIdentityCheckFalse;
			ds.PhoneValidateFalse=json['data'].PhoneValidateFalse;
			ds.Degree=json['data'].Degree;
			ds.StudyStyle=json['data'].StudyStyle;
			ds.School=json['data'].School;
			ds.Gender=json['data'].Gender;
			ds.Age=json['data'].Age;
			ds.AgeRangeA=json['data'].AgeRangeA;
			ds.AgeRangeB=json['data'].AgeRangeB;
			ds.AvgBorrowIntervalA=json['data'].AvgBorrowIntervalA;
			ds.AvgBorrowIntervalB=json['data'].AvgBorrowIntervalB;
			ds.LastSuccessIntervalA=json['data'].LastSuccessIntervalA;
			ds.LastSuccessIntervalB=json['data'].LastSuccessIntervalB;
			ds.CurAvgIntervalRatioA=json['data'].CurAvgIntervalRatioA;
			ds.CurAvgIntervalRatioB=json['data'].CurAvgIntervalRatioB;
			ds.FirstSuccessIntervalA=json['data'].FirstSuccessIntervalA;
			ds.FirstSuccessIntervalB=json['data'].FirstSuccessIntervalB;
			ds.RegisterFirstIntervalA=json['data'].RegisterFirstIntervalA;
			ds.RegisterFirstIntervalB=json['data'].RegisterFirstIntervalB;
			ds.RegisterMonthA=json['data'].RegisterMonthA;
			ds.RegisterMonthB=json['data'].RegisterMonthB;
			ds.SuccessCountA=json['data'].SuccessCountA;
			ds.SuccessCountB=json['data'].SuccessCountB;
			ds.WasteCountA=json['data'].WasteCountA;
			ds.WasteCountB=json['data'].WasteCountB;
			ds.NormalCountA=json['data'].NormalCountA;
			ds.NormalCountB=json['data'].NormalCountB;
			ds.NormalSuccessRatioA=json['data'].NormalSuccessRatioA;
			ds.NormalSuccessRatioB=json['data'].NormalSuccessRatioB;
			ds.DelayNormalRatioA=json['data'].DelayNormalRatioA;
			ds.DelayNormalRatioB=json['data'].DelayNormalRatioB;
			ds.OverdueCountA=json['data'].OverdueCountA;
			ds.OverdueCountB=json['data'].OverdueCountB;
			ds.OverdueMoreCountA=json['data'].OverdueMoreCountA;
			ds.OverdueMoreCountB=json['data'].OverdueMoreCountB;
			ds.OwingAmountA=json['data'].OwingAmountA;
			ds.OwingAmountB=json['data'].OwingAmountB;
			ds.OwingAfterAmountA=json['data'].OwingAfterAmountA;
			ds.OwingAfterAmountB=json['data'].OwingAfterAmountB;
			ds.OwingPrevHighDebtRatioA=json['data'].OwingPrevHighDebtRatioA;
			ds.OwingPrevHighDebtRatioB=json['data'].OwingPrevHighDebtRatioB;
			ds.OwingHighDebtRatioA=json['data'].OwingHighDebtRatioA;
			ds.OwingHighDebtRatioB=json['data'].OwingHighDebtRatioB;
			ds.LastHighestBorrowRatioA=json['data'].LastHighestBorrowRatioA;
			ds.LastHighestBorrowRatioB=json['data'].LastHighestBorrowRatioB;
			ds.TotalBorrowA=json['data'].TotalBorrowA;
			ds.TotalBorrowB=json['data'].TotalBorrowB;
			ds.OwnPreTotalBorrowRatioA=json['data'].OwnPreTotalBorrowRatioA;
			ds.OwnPreTotalBorrowRatioB=json['data'].OwnPreTotalBorrowRatioB;
			ds.CurAmountTotalBorrowRatioA=json['data'].CurAmountTotalBorrowRatioA;
			ds.CurAmountTotalBorrowRatioB=json['data'].CurAmountTotalBorrowRatioB;
			ds.AvgBorrowAmountA=json['data'].AvgBorrowAmountA;
			ds.AvgBorrowAmountB=json['data'].AvgBorrowAmountB;
			ds.CurAmountAvgBorrowRatioA=json['data'].CurAmountAvgBorrowRatioA;
			ds.CurAmountAvgBorrowRatioB=json['data'].CurAmountAvgBorrowRatioB;
			ds.BidAmount=json['data'].BidAmount;
			ds.DayAmountLimit=json['data'].DayAmountLimit;
			
			ds.HighDebtA=json['data'].HighDebtA;
			ds.HighDebtB=json['data'].HighDebtB;
			ds.OwingAmountRatioA=json['data'].OwingAmountRatioA;
			ds.OwingAmountRatioB=json['data'].OwingAmountRatioB;
			ds.WasteNormalRatioA=json['data'].WasteNormalRatioA;
			ds.WasteNormalRatioB=json['data'].WasteNormalRatioB;
			ds.CancelCountA=json['data'].CancelCountA;
			ds.CancelCountB=json['data'].CancelCountB;
			ds.FailCountA=json['data'].FailCountA;
			ds.FailCountB=json['data'].FailCountB;
			ds.TailNumber10=json['data'].TailNumber10;
			
			showSetting(ds);
		}
	});
}
$(document).ready(function(){
	CreateDefaultSetting();
	showSetting(ds);
	getDsFromServer(ds);
	
	//注册 保存按钮
	$("#strategy_diy_save").click( function() {
		if(!checkParam(ds))
		{
			alert("您的策略设置不当，请按照右侧红字提示修改！");
		}else{
			showSetting(ds);
			doSubmit(ds);
		}
	 });
	
	//注册 保存按钮
	$("#strategy_diy_test").click( function() {
		if(!checkParam(ds))
		{
			alert("您的策略设置不当，请按照右侧红字提示修改！");
		}else{
			showSetting(ds);
			doTestDiy(ds);
		}
	 });
	delayRegisterAction();
	
	

});
