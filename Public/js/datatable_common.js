function getDTConfig(settings){
  var dtConfig = {
    "paging":true,
    "pagingType":"full_numbers",
    "lengthMenu":[10,20,50],
    "processing": true,
    "searching": false, //是否开启搜索
    "serverSide": true,//开启服务器获取数据
    "order": [[ 0, "desc" ]], //默认排序
    "ordering": false,
    "dom": 'rtip',
    "data": 2,
    ajax: {
      url: settings.ajax_url,
      type:'POST',
      cache: true,
      dataSrc:function(json){
        var dataSet = new Array();
        dataSet = json.data;
        return dataSet;
      },
      data:settings.ajax_data
    },
    columnDefs:settings.column_defs,
    "language":{ // 定义语言
      "sProcessing":"加载中...",
      "sLengthMenu":"每页显示 _MENU_ 条记录",
      "sZeroRecords":"没有匹配的结果",
      "sInfo": "_START_ -  _END_，共 _TOTAL_ 项",
      "sInfoEmpty": "显示第 0 至 0 项结果，共 0 项",
      "sInfoFiltered": "(由 _MAX_ 项结果过滤)",
      "sInfoPostFix": "",
      "sSearch": "搜索:",
      "sUrl": "",
      "sEmptyTable": "表中数据为空",
      "sLoadingRecords": "载入中...",
      "sInfoThousands": ",",
      "oPaginate": {
        "sFirst": "首页",
        "sPrevious": "上一页",
        "sNext": "下一页",
        "sLast": "末页"
      },
      "oAria": {
        "sSortAscending": ": 以升序排列此列",
        "sSortDescending": ": 以降序排列此列"
      }
    },
    responsive: true
  };
  return dtConfig;
}


