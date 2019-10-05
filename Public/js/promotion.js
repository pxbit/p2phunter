
function showCoupon(){
	 $.post(Think.U('Home/User/getCouponRecord'),{},function(data,textStatus){
		  var json = PPD.toJson(data);
		  var str="<tbody><tr><th align=\"left\">获赠雕币</th><th>获得原因</th><th>获得日期</th></tr>";
		  if( json['status']=="OK"){
			  for(var id in json['data']){
					var coupon=json['data'][id];
					str = str 
					+ "<tr>" 
					+ "<td align=\"left\" >"  +  coupon.TotalQuota + "</td>" 
					+ "<td >" + coupon.Reason + "</td>"
					+ "<td >" + coupon.ObtainDate + "</td>"
					+ "</tr>"
			  }
		  }else{
			  str = str + "<tr><td>你还没有获得优惠券，</td><td>把金雕推荐给您朋友吧，</td><td>你和他都会得到优惠券。</td></tr>";
		  }
		  str = str + + "</tbody>";
		  $("#promotion_list").html(str);
	 })
	
}

$(document).ready(function(){
	showCoupon();
});