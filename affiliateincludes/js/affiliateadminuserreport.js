function showTooltip(x, y, contents) {
   jQuery('<div id="afftooltip">' + contents + '</div>').css( {
		position: 'absolute',
        display: 'none',
        top: y + 5,
        left: x + 5,
        border: '1px solid #fdd',
        padding: '2px',
        'background-color': '#fee',
        opacity: 0.80
   }).appendTo("body").fadeIn(200);
}

function affSetWidth() {
	var width = jQuery('#affdashgraph').parents('#clickscolumn').width();
	//jQuery('#affdashgraph').width((width - 15) + 'px');

}

function affReBuildChart(chart, ticks) {
	var options = {
	    lines: { show: true },
	    points: { show: true },
		grid: { hoverable: true, backgroundColor: { colors: ["#fff", "#eee"] } },
		xaxis: { tickDecimals: 0, ticks: ticks},
		yaxis: { tickDecimals: 0, min: 0},
		legend: {
		    show: true,
		    position: "nw" }
	  };

	affplot = jQuery.plot(jQuery('#affdashgraph'), chart, options);

	var previousPoint = null;
	jQuery("#affdashgraph").bind("plothover", function (event, pos, item) {
	    if (item) {
	    	if (previousPoint != item.datapoint) {
	        	previousPoint = item.datapoint;

	            jQuery("#afftooltip").remove();
				jQuery("tr.periods").css('background', '#FFF');
	            var x = item.datapoint[0].toFixed(0),
	            	y = item.datapoint[1].toFixed(0);

	                showTooltip(item.pageX, item.pageY,
	                            y + ' ' + item.series.label);

					jQuery('#period-' + item.datapoint[0]).css('background', '#DEE7F8');
	        }
		} else {
	    	jQuery("#afftooltip").remove();
			jQuery("tr.periods").css('background', '#FFF');
			previousPoint = null;
		}
	});
}

function affDoPlot() {

	if(jQuery('#affdashgraph').length == 0) {
		return;
	}

	if(chart) {
		affReBuildChart(chart, ticks);
	} else {
		var userid = jQuery('#payuserid').val();
		jQuery.getJSON(ajaxurl, { action: '_aff_getstats', userid: userid, number: 10 },
		        function(data){

					if(data.chart) { chart = data.chart; } else { chart = []; }
					if(data.ticks) { ticks = data.ticks; } else { ticks = []; }

					affReBuildChart(chart, ticks);
		        });
	}

}

function affReportReady() {

	chart = false;
	ticks = false;


	affSetWidth();
	affDoPlot();

	jQuery(window).resize( function() {
		affSetWidth();
		affDoPlot();
	});



}


jQuery(document).ready(affReportReady);