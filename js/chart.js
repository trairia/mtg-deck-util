
google.load('visualization', '1.0', {'packages': ['corechart']});
google.setOnLoadCallback(chart_drawer.draw);

var Chart = function(){
    this.charts = [];
    this.data = [];
    this.options = [];
};

Chart.prototype.add_chart = function (id, func) {
    var data_json = document.getElementById(id).getAttribute('data');
    var data = JSON.parse(data_json);
    this.data.push(google.visualization.arrayToDataTable(data));
    var opt_json = document.getElementById(id).getAttribute('data_opt');
    var opt = JSON.parse(opt_json);
    this.options.push(opt);
    var chart = new func(document.getElementById(id));
    this.charts.push(chart);
};

Chart.prototype.draw = function(){
    for(i=0; i<this.charts.length; i++){
	this.charts[i].draw(this.data[i], this.options[i]);
    }
};

chart_drawer = new Chart();
chart_drawer.add_chart('manacurve', google.visualization.ColumnChart);
chart_drawer.add_chart('colorpie', google.visualization.PieChart);
chart_drawer.add_chart('typepie', google.visualization.PieChart);
