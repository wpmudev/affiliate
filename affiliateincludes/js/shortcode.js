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
	var width = jQuery('#affdashgraph').parents('div.wrap').width();
	//jQuery('#affdashgraph').width((width - 15) + 'px');

	//affvisitgraph
	var colwidth = jQuery('#affvisitgraph').parents('div#referrerscolumn').width();
	//jQuery('#affvisitgraph').width((colwidth - 10) + 'px');
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
		    position: "nw",
			container: "#affdashlegend"
		 }
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

function affReBuildVisits(chart, ticks) {
	var options = {
	    lines: { show: true },
	    points: { show: true },
		grid: { hoverable: true, backgroundColor: { colors: ["#fff", "#eee"] } },
		xaxis: { tickDecimals: 0, ticks: ticks},
		yaxis: { tickDecimals: 0, min: 0},
		legend: {
		    show: true,
		    position: "nw",
			container: "#affvisitlegend"
		}
	  };

	affplot = jQuery.plot(jQuery('#affvisitgraph'), chart, options);

	var previousPoint = null;
	jQuery("#affvisitgraph").bind("plothover", function (event, pos, item) {
	    if (item) {
	    	if (previousPoint != item.datapoint) {
	        	previousPoint = item.datapoint;

	            jQuery("#afftooltip").remove();
	            var x = item.datapoint[0].toFixed(0),
	            	y = item.datapoint[1].toFixed(0);

	                showTooltip(item.pageX, item.pageY,
	                            y + ' visits');
	        }
		} else {
	    	jQuery("#afftooltip").remove();
			previousPoint = null;
		}
	});
}

function affDoPlot() {

	if (jQuery('#affdashgraph').length) {
		if(chart) {
			affReBuildChart(chart, ticks);
		} else {
			jQuery.getJSON(affiliate.ajaxurl, { action: '_aff_getstats' }, function(data) {

				if(data.chart) { chart = data.chart; } else { chart = []; }
				if(data.ticks) { ticks = data.ticks; } else { ticks = []; }

				affReBuildChart(chart, ticks);
			});
		}
	}

	if (jQuery('#affvisitgraph').length) {
		if(visit) {
			affReBuildVisits(visit, ticktwo);
		} else {
			jQuery.getJSON(affiliate.ajaxurl, { action: '_aff_getvisits' }, function(data) {

				if(data.chart) { visit = data.chart; } else { visit = []; }
				if(data.ticks) { ticktwo = data.ticks; } else { ticktwo = []; }

				affReBuildVisits(visit, ticktwo);

			});
		}
	}
}

function affToggleView() {
	jQuery('div.formholder div.innerbox').slideToggle('slow').toggleClass('closed').toggleClass('open');
}

function affReportReady() {

	chart = false;
	ticks = false;

	visit = false;
	ticktwo = false;

	affSetWidth();
	affDoPlot();

	jQuery(window).resize( function() {
		affSetWidth();
		affDoPlot();
	});

	jQuery('#editaffsettingslink').click(affToggleView);

}


jQuery(document).ready(affReportReady);