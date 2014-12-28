// -*- coding:utf-8-unix -*-
google.load("visualization", "1", {packages:"corechart"});
google.setOnLoadCallback(draw);

var ColorConv={'G':'green', 'B':'black', 'U':'blue', 'R': 'red', 'W': 'white', 'colorless':'gray',
	      'multicolor': 'yellow'};

function draw(){
    var manacurve = JSON.parse(document.getElementById('manacurve').getAttribute("data"));
    var mdata = google.visualization.arrayToDataTable(manacurve);
    var colorpie = JSON.parse(document.getElementById('colorpie').getAttribute("data"));
    var cdata = google.visualization.arrayToDataTable(colorpie);
    var mchart = new google.visualization.ColumnChart(document.getElementById('manacurve'));
    var options = {
	bar: { groupWidth: '75%'},
	legend: 'none',
    };
    var pie_options = []
    for (var i = 0; i < colorpie.length-1; i++){
	pie_options[i] = { color : ColorConv[colorpie[i+1][0]]};
    }
    var coptions = {
	slices: pie_options
    }
    var cchart = new google.visualization.PieChart(document.getElementById('colorpie'));
    mchart.draw(mdata);
    cchart.draw(cdata, coptions);
}

