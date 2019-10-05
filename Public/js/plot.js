
function plotchart(settings, canvasid){
    var config = getChartConfig(settings);
    if ($("#mbl").val() == 1){
        config.options.maintainAspectRatio = false;
    } else {
        config.options.maintainAspectRatio = true;
    }

    var ctx   = document.getElementById(canvasid).getContext("2d");
    var chart = new Chart(ctx, config);
}
