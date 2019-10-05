function getChartConfig(settings){
  var config ={
    type: 'line',    /* 缺省类型, 后续通过程序动态配置 */
    data: {},        /* 缺省为空对象，后续通过程序动态配置*/
    options:
    {
      responsive: true,
      layout: {
        padding: {
          left: 0,
          right: 0,
          top: 0,
          bottom: 0
        }
      },
      title:{
        display:false,
        text: 'mychart'
      },
      legend: {
        display: true,
        labels: {
          fontColor: 'rgb(255, 99, 132)'
        }
      },
      tooltips: {
        mode: 'index',
        intersect: false,
      },
      hover: {
        mode: 'nearest',
        intersect: true
      },
      scales: {
        xAxes: [{
          maxBarThickness:20,
          display: true,
          scaleLabel: {
            display: true,
            labelString:""
          },
          gridLines: {
            display: true,
          }
        }],
        yAxes: [{
          display: true,
          scaleLabel: {
            display: true,
            labelString:""
          },
          gridLines: {
            display: true,
          }
        }]
      }
    }
  };

  config.type = settings.type;
  config.data = getChartData(settings);
  if((settings.type == 'line')){
    config.options.title.text = settings.title_text;
    config.options.scales.xAxes[0].scaleLabel.labelString = settings.xAxesLabel;
    config.options.scales.yAxes[0].scaleLabel.labelString = settings.yAxesLabel;

  }else if(settings.type=='bar'){
    config.options.title.display  = settings.title_display;
    config.options.title.text     = settings.title_text;
    config.options.legend.display = settings.legend_display;
    config.options.scales.xAxes[0].scaleLabel.labelString = settings.xAxesLabel;
    config.options.scales.yAxes[0].scaleLabel.labelString = settings.yAxesLabel;

  } else if(settings.type=='pie'){
    config.options.title.display   = false;
    config.options.legend.position = 'top';
    config.options.scales.xAxes[0].display = false;
    config.options.scales.yAxes[0].display = false;
  }
  return config;
}


function getChartData(settings){
  var data = {};
  var dtsets = new Array();

  $.each(settings.y, function(index, val){
    var singleSet = {};
    /*公共属性*/
    var singleSet = {
      data             : val.data,
      label            : val.title,
      backgroundColor  : val.color,
      borderColor      : val.color,
      borderWidth      : 1,
    };
    /*个体属性*/
    if(settings.type == 'line'){
      singleSet.fill                      = false;
      singleSet.lineTension               = 0;
      singleSet.borderCapStyle            = 'butt';
      singleSet.borderDash                = [];
      singleSet.borderDashOffset          = 0.0;
      singleSet.borderJoinStyle           = 'miter';
      singleSet.pointBorderColor          = val.color;
      singleSet.pointBackgroundColor      = val.color;
      singleSet.pointBorderWidth          = 1;
      singleSet.pointHoverRadius          = 5;
      singleSet.pointHoverBackgroundColor = "rgba(75,192,192,1)";
      singleSet.pointHoverBorderColor     = "rgba(220,220,220,1)";
      singleSet.pointHoverBorderWidth     = 2;
      singleSet.pointRadius               = 1;
      singleSet.pointHitRadius            = 10;
    }else if(settings.type == 'bar'){
      /*使用chart.js缺省*/
    }else if(settings.type == 'pie'){
      /*使用chart.js缺省*/
    }
    dtsets.push(singleSet);
  });

  data.datasets = dtsets;
  data.labels   = settings.x;
  return data;
}

