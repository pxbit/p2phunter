function checkMobileFormat() {
  var mobile = $("#mobile").val();
  var partten = /^(((13[0-9]{1})|(14[0-9]{1})|(17[0]{1})|(15[0-3]{1})|(15[5-9]{1})|(18[0-9]{1}))+\d{8})$/;
  if (!partten.test(mobile)) {
    return false;
  }
  return true;
}

function chkformReg() {
  var mobile         = $("#mobile").val();
  var mobilecode     = $("#mobileVerifyCode").val();
  var verfcode       = $("#verifycode").val();
  /* 空值检查 */
  if (mobile == "") {
    $("#mobile").attr('placeholder', '手机号不能为空');
    return false;
  }
  /* 有效性检查 */
  if(!checkMobileFormat()){
    $('#checkMobile').css('color', 'red');
    $('#checkMobile').html("手机号错误!");
    return false;
  }
  /* 空值检查 */
  if (mobilecode == "") {
    $("#mobileVerifyCode").attr('placeholder', '手机验证码不能为空');
    return false;
  }
  /* 有效性检查 */
  if ($("#isMobileVerCodePass").val() == 0) {
    alert("手机验证码不匹配");
    return false;
  }
  /* 空值检查 */
  if (verfcode == "") {
    $("#verifycode").attr('placeholder', '验证码不能为空');
    return false;
  }
  /* 有效性检查 */
  if ($("#isVefPass").val() == 0) {
    $("#verifycode").attr('placeholder', '验证码错误');
    $('#checkVerifyCode').css('color', 'red');
    $('#checkVerifyCode').html('*验证码错误');
    return false;
  }
  $("#regform").submit();
  return true;
}

$(document).ready(function(){
  $("#btnGetMobileVerifyCode").click(function() {
    var mobile = $("#mobile").val();
    if (mobile == "") {
      $("#mobile").attr('placeholder', '请输入手机号');
      return false;
    }
    if(!checkMobileFormat()){
      $('#checkMobile').css('color', 'red');
      $('#checkMobile').html("手机号错误!");
      return false;
    }
    var htmlobj = $.ajax({
      type: "POST",
      url:  "sendMobileVerifyCode",
      data: {
        mobile: $('#mobile').val()
      },
      success: function(data) {
        if(data == -52){
          alert("1分钟内不能重复发送！");
          return;
        }else if(data == -51) {
          alert("验证码发送失败, 检查输入的手机号，1分钟后重试!");
          return;
        }
        alert("验证码已发送.");
      },
      complete: function(){}
    });
  });

  /* 单击更换验证码 */
  $("#verify_img").click(function() {
    var verifyURL = "verify";
    var time = new Date().getTime();
    $("#verify_img").attr({
      "src": verifyURL + "/" + time
    });
  });

  /* 实时检查手机号格式 */
  $("#mobile").keyup(function() {
    $('#checkMobile').css('color', 'green');
    var mobile = $("#mobile").val();
    var partten = /^\d*$/;
    /* 检查是否为数字 */
    if (!partten.test(mobile)) {
      $('#checkMobile').css('color', 'red');
      $('#checkMobile').html("输入错误!");
      return false;
    }
    /* 恢复正确提示*/
    $('#checkMobile').html("*");
    return true;
  }); 

  /* 实时检查验证码 */
  $("#verifycode").keyup(function() {
    $('#checkVerifyCode').css('color', 'green');
    var value = $('#verifycode').val().length;
    if (value < 4) {
      $('#vef').html('*');
      return false;
    }
    $.post("check_verify", {
      code: $("#verifycode").val()
    }, function(data) {
      if (data == true) {
        $('#checkVerifyCode').css('color', 'green');
        $('#checkVerifyCode').html('*验证码正确');
        $("#isVefPass").val(1);
        return true;
      } else {
        $('#checkVerifyCode').css('color', 'red');
        $('#checkVerifyCode').html('*验证码错误');
        $("#isVefPass").val(0);
        return false;
      }
    });
    return true;
  });

  $("#mobileVerifyCode").keyup(function(){
    var len = $('#mobileVerifyCode').val().length;
    if(len < 4){
      $("#isMobileVerCodePass").val(0);
      return false;
    }
    $.post(Think.U("Home/UserReg/checkMobileVerifyCode"), {
      mobileVerifyCode:$("#mobileVerifyCode").val()
    }, function(data){
      $("#isMobileVerCodePass").val(data);
    })
  });
}); //jquery->document ready end
