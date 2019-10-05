
function styleCommonDOM(){
  /* 绘图区域自适用分辨率 */
  var canvas = $(".box,.canvas");
  var width = $(window).width();
  if (width < 350){
    canvas.css("height","50vh");
  }else if (width < 400){
    canvas.css("height","350px");
  } else if(width < 500) {
    canvas.css("height","400px");
  } else if(width < 700){
    canvas.css("height","500px");
  } else {
    canvas.css("height","60vh");
  }
}

$(document).ready(function(){
  styleCommonDOM();
});
