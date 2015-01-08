
google.load('visualization', '1.0', {'packages': ['corechart']});
google.setOnLoadCallback(drawChart);

function drawChart(){
    var types = [['manacurve', 'column'],
		 ['colorpie', 'pie'],
		 ['typepie', 'pie']];
    for(i = 0; i < types.length; i++){
	var div = document.getElementById(types[i][0]);
	if (!div)
	    continue;
	var data_json = JSON.parse(div.getAttribute('data'));
	if (!data_json)
	    continue;
	var data = google.visualization.arrayToDataTable(
	    data_json
	);
	var opt_json = div.getAttribute('data_opt');
	var opt = null;
	if (opt_json){
	    opt = JSON.parse(opt_json);
	}
	if (opt){
	    if(types[i][1] === 'column'){
		opt['hAxis'] = {ticks:[]};
		for(j = 0; j < data_json.length-1; j++){
		    opt.hAxis.ticks.push(j);
		}
	    }
	}
	var chart = null;
	switch(types[i][1]){
	case 'column':
	    chart = new google.visualization.ColumnChart(document.getElementById(types[i][0]));
	    break;
	case 'pie':
	    chart = new google.visualization.PieChart(document.getElementById(types[i][0]));
	    break;
	default:
	    break;
	}
	if (chart){
	    chart.draw(data, opt);
	}
    }
}
