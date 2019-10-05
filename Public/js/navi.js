$(document).ready(function(){
	var root = "__ROOT__";
  $("#header_navi_item2").click(function(){
	  location.href = ROOT+"/home/user.html";
  });
  $("#header_navi_item3").click(function(){
	  location.href = ROOT+"/home/strategy.html";
  });
  $("#header_navi_item4").click(function(){
	  location.href = ROOT+"/home/analysis.html";
  });
  $("#header_navi_item5").click(function(){
	  location.href = ROOT+"/home/help.html";
  });
  $("#header_navi_item6").click(function(){
	  location.href = ROOT+"/home/about.html";
  });
  $("#footer_register").click(function(){
	  window.open("http://www.ppdai.com/regt/szlemail");
  });
  $("#footer_login").click(function(){
	  location.href = "https://ac.ppdai.com/oauth2/login?AppID=8eda97b423dd4a33aeaf0d6dddc0aac3&ReturnUrl=http://"+document.domain+"/home/user/ppd";
  });
  $("#header_login_out").click(function(){
	  location.href = ROOT+"/home/user/logout.html";
  })
    $(".header_logo_pic").click(function(){
	  location.href = ROOT+"/home/index.html";
  })
  
	var url=window.location.href;
  	url=url.replace("/index.php", "");
	var params=ThinkPHP.parse_url(url);
	if(params['path']=="/home/strategy")
		$("#header_navi_item3").addClass('select');
	else if(params['path']=="/home/analysis")
		$("#header_navi_item4").addClass('select');
	else if(params['path']=="/home/help")
		$("#header_navi_item5").addClass('select');
	else if(params['path']=="/home/about")
		$("#header_navi_item6").addClass('select');
	else
		$("#header_navi_item2").addClass('select');
  
});