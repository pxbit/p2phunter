$(document).ready(function(){
  /* 对于测试机器, 这里的数据不直接计算，直接从主服务获取 */
  // $.post(Think.U('Home/Strategy/getSysStatsFromRemote'));

  $('#tbl-stat').DataTable({
    "ajax": {
      "url":Think.U('Home/Strategy/getSysStrategyStats'),
      //"data":{}, /* data和datasrc同时存在的时候，会优先从datasrc中获取数据 */
      "dataSrc": function(data){
        var stats = data.stats;

        for (var i in stats){
          var obj = stats[i];
          Object.keys(obj).forEach(function(key){
            if (key == 'AVGR'){
              var val = (obj[key] * 100).toFixed(2) + '%';
              obj[key] = val;
            }

            if (key == 'D30' || key =='D90'){
              var val = obj[key];
              obj[key] = (val < 0) ? '-' : (100 * val).toFixed(2) + '%';
            }

          });

          obj['FD30'] = obj['FD30'] + '/' + obj['FA30'];
          obj['SD30'] = obj['SD30'] + '/' + obj['SA30'];
          obj['TD30'] = obj['TD30'] + '/' + obj['TA30'];
        }

        var d = new Date()
        var hours = Math.round((d.getTime()/1000 - data.time)/60/60)
        $("#update-time").html(" " + hours + "小时之前.");
        return stats;
      }
    },
    "columns": [
      { "data": "Name"}, /* 策略名 */
      { "data": "AVGR"}, /* 平均投资利率 */
      { "data": "D30"},  /* 30日逾期率 */
      { "data": "D90"},  /* 90日逾期率 */
      { "data": "AVGM"}, /* 平均投资期限 */
      { "data": "FD30"}, /* 1期30日逾期 */
      { "data": "SD30"}, /* 2期30日逾期 */
      { "data": "TD30"}, /* 3期30日逾期 */
    ],
    ordering: false,
    pageLength: 20,
    responsive: true,
    dom: 'rtip'
  });

});

