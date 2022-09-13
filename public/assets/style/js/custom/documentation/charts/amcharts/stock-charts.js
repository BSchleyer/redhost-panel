/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!****************************************************************************!*\
  !*** ../demo8/src/js/custom/documentation/charts/amcharts/stock-charts.js ***!
  \****************************************************************************/


// Class definition
var KTGeneralAmChartsStock = function () {
    // Shared variables
    var chart;
    const bodyColor = getComputedStyle(document.documentElement).getPropertyValue('--bs-body-color');
    const bgColor = getComputedStyle(document.documentElement).getPropertyValue('--bs-body-bg');

    // Private functions
    var _demo1 = function () {
        // Init AmChart -- for more info, please visit the official documentiation: https://www.amcharts.com/docs/v5/getting-started/
        am5.ready(function () {

            // Create root element
            // https://www.amcharts.com/docs/v5/getting-started/#Root_element
            var root = am5.Root.new("kt_amcharts_1");


            // Set themes
            // https://www.amcharts.com/docs/v5/concepts/themes/
            root.setThemes([
                am5themes_Animated.new(root)
            ]);


            // Create chart
            // https://www.amcharts.com/docs/v5/charts/xy-chart/
            var chart = root.container.children.push(am5xy.XYChart.new(root, {
                panX: true,
                panY: false,
                wheelX: "panX",
                wheelY: "zoomX",
                layout: root.verticalLayout
            }));

            chart.get("colors").set("step", 2);


            // Create axes
            // https://www.amcharts.com/docs/v5/charts/xy-chart/axes/
            // when axes are in opposite side, they should be added in reverse order
            var volumeAxisRenderer = am5xy.AxisRendererY.new(root, {
                opposite: true
            });
            volumeAxisRenderer.labels.template.setAll({
                centerY: am5.percent(100),
                maxPosition: 0.98,
                fill: bodyColor
            });
            var volumeAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
                renderer: volumeAxisRenderer,
                height: am5.percent(30),
                layer: 5
            }));
            volumeAxis.axisHeader.set("paddingTop", 10);
            volumeAxis.axisHeader.children.push(am5.Label.new(root, {
                text: "Volume",
                fontWeight: "bold",
                paddingTop: 5,
                paddingBottom: 5
            }));

            var valueAxisRenderer = am5xy.AxisRendererY.new(root, {
                opposite: true,
                pan: "zoom"
            });
            valueAxisRenderer.labels.template.setAll({
                centerY: am5.percent(100),
                maxPosition: 0.98,
                fill: bodyColor
            });
            var valueAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
                renderer: valueAxisRenderer,
                height: am5.percent(70),
                maxDeviation: 1
            }));
            valueAxis.axisHeader.children.push(am5.Label.new(root, {
                text: "Value",
                fontWeight: "bold",
                paddingBottom: 5,
                paddingTop: 5
            }));



            var dateAxisRenderer = am5xy.AxisRendererX.new(root, {
                pan: "zoom"
            });
            dateAxisRenderer.labels.template.setAll({
                minPosition: 0.01,
                maxPosition: 0.99,
                fill: bodyColor
            });
            var dateAxis = chart.xAxes.push(am5xy.DateAxis.new(root, {
                groupData: true,
                maxDeviation: 0.5,
                baseInterval: {
                    timeUnit: "day",
                    count: 1
                },
                renderer: dateAxisRenderer
            }));
            dateAxis.set("tooltip", am5.Tooltip.new(root, {}));


            // Add series
            // https://www.amcharts.com/docs/v5/charts/xy-chart/series/
            var valueSeries1 = chart.series.push(am5xy.LineSeries.new(root, {
                name: "XTD",
                valueYField: "price1",
                calculateAggregates: true,
                valueXField: "date",
                xAxis: dateAxis,
                yAxis: valueAxis,
                legendValueText: "{valueY}"
            }));

            var valueTooltip = valueSeries1.set("tooltip", am5.Tooltip.new(root, {
                getFillFromSprite: false,
                getStrokeFromSprite: true,
                getLabelFillFromSprite: true,
                autoTextColor: false,
                pointerOrientation: "horizontal",
                labelText: "{name}: {valueY} {valueYChangePercent.formatNumber('[#00ff00]+#,###.##|[#ff0000]#,###.##|[#999999]0')}%"
            }));
            valueTooltip.get("background").set("fill", bgColor);

            var firstColor = chart.get("colors").getIndex(0);
            var volumeSeries = chart.series.push(am5xy.ColumnSeries.new(root, {
                name: "XTD",
                fill: firstColor,
                stroke: firstColor,
                valueYField: "quantity",
                valueXField: "date",
                valueYGrouped: "sum",
                xAxis: dateAxis,
                yAxis: volumeAxis,
                legendValueText: "{valueY}",
                tooltip: am5.Tooltip.new(root, {
                    labelText: "{valueY}"
                })
            }));
            volumeSeries.columns.template.setAll({
                strokeWidth: 0.2,
                strokeOpacity: 1,
                stroke: am5.color(0xffffff)
            });


            // Add legend to axis header
            // https://www.amcharts.com/docs/v5/charts/xy-chart/axes/axis-headers/
            // https://www.amcharts.com/docs/v5/charts/xy-chart/legend-xy-series/
            var valueLegend = valueAxis.axisHeader.children.push(
                am5.Legend.new(root, {
                    useDefaultMarker: true
                })
            );
            valueLegend.data.setAll([valueSeries1]);

            var volumeLegend = volumeAxis.axisHeader.children.push(
                am5.Legend.new(root, {
                    useDefaultMarker: true
                })
            );
            volumeLegend.data.setAll([volumeSeries]);


            // Stack axes vertically
            // https://www.amcharts.com/docs/v5/charts/xy-chart/axes/#Stacked_axes
            chart.rightAxesContainer.set("layout", root.verticalLayout);


            // Add cursor
            // https://www.amcharts.com/docs/v5/charts/xy-chart/cursor/
            chart.set("cursor", am5xy.XYCursor.new(root, {}))


            // Add scrollbar
            // https://www.amcharts.com/docs/v5/charts/xy-chart/scrollbars/
            var scrollbar = chart.set("scrollbarX", am5xy.XYChartScrollbar.new(root, {
                orientation: "horizontal",
                height: 50
            }));

            var sbDateAxis = scrollbar.chart.xAxes.push(am5xy.DateAxis.new(root, {
                groupData: true,
                groupIntervals: [{
                    timeUnit: "week",
                    count: 1
                }],
                baseInterval: {
                    timeUnit: "day",
                    count: 1
                },
                renderer: am5xy.AxisRendererX.new(root, {})
            }));

            var sbValueAxis = scrollbar.chart.yAxes.push(
                am5xy.ValueAxis.new(root, {
                    renderer: am5xy.AxisRendererY.new(root, {})
                })
            );

            var sbSeries = scrollbar.chart.series.push(am5xy.LineSeries.new(root, {
                valueYField: "price1",
                valueXField: "date",
                xAxis: sbDateAxis,
                yAxis: sbValueAxis
            }));

            sbSeries.fills.template.setAll({
                visible: true,
                fillOpacity: 0.3
            });


            // Generate random data and set on series
            // https://www.amcharts.com/docs/v5/charts/xy-chart/series/#Setting_data
            var data = [];
            var price1 = 1000;
            var quantity = 10000;

            for (var i = 1; i < 5000; i++) {
                price1 += Math.round((Math.random() < 0.5 ? 1 : -1) * Math.random() * 20);

                if (price1 < 100) {
                    price1 = 100;
                }

                quantity += Math.round((Math.random() < 0.5 ? 1 : -1) * Math.random() * 500);

                if (quantity < 0) {
                    quantity *= -1;
                }
                data.push({
                    date: new Date(2010, 0, i).getTime(),
                    price1: price1,
                    quantity: quantity
                });
            }

            valueSeries1.data.setAll(data);
            volumeSeries.data.setAll(data);
            sbSeries.data.setAll(data);


            // Make stuff animate on load
            // https://www.amcharts.com/docs/v5/concepts/animations/
            chart.appear(1000, 100);

        }); // end am5.ready()
    }

    var _demo2 = function () {
        // Init AmChart -- for more info, please visit the official documentiation: https://www.amcharts.com/docs/v5/getting-started/
        am5.ready(function () {

            // Create root element
            // https://www.amcharts.com/docs/v5/getting-started/#Root_element
            var root = am5.Root.new("kt_amcharts_2");


            // Set themes
            // https://www.amcharts.com/docs/v5/concepts/themes/
            root.setThemes([
                am5themes_Animated.new(root)
            ]);


            // Create chart
            // https://www.amcharts.com/docs/v5/charts/xy-chart/
            var chart = root.container.children.push(am5xy.XYChart.new(root, {
                panX: true,
                panY: false,
                wheelX: "panX",
                wheelY: "zoomX",
                layout: root.verticalLayout
            }));

            chart.get("colors").set("step", 2);


            // Create axes
            // https://www.amcharts.com/docs/v5/charts/xy-chart/axes/

            // Y axis #1
            var valueAxisRenderer = am5xy.AxisRendererY.new(root, {
                opposite: true,
                pan: "zoom"
            });

            valueAxisRenderer.labels.template.setAll({
                centerY: am5.percent(100),
                maxPosition: 0.98,
                fill: bodyColor
            });

            var valueAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
                renderer: valueAxisRenderer,
                maxDeviation: 1,
                extraMin: 0.2 // gives some extra space
            }));

            // Y axis #2
            var volumeAxisRenderer = am5xy.AxisRendererY.new(root, {
                opposite: true
            });

            volumeAxisRenderer.labels.template.setAll({
                forceHidden: true,
                fill: bodyColor
            });

            volumeAxisRenderer.grid.template.setAll({
                forceHidden: true
            });

            var volumeAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
                renderer: volumeAxisRenderer,
                height: am5.percent(25),
                layer: 5,
                centerY: am5.p100,
                y: am5.p100
            }));

            volumeAxis.axisHeader.set("paddingTop", 10);


            // X axis
            var dateAxisRenderer = am5xy.AxisRendererX.new(root, {});

            dateAxisRenderer.labels.template.setAll({
                minPosition: 0.01,
                maxPosition: 0.99,
                fill: bodyColor
            });

            var dateAxis = chart.xAxes.push(am5xy.DateAxis.new(root, {
                groupData: true,
                baseInterval: {
                    timeUnit: "day",
                    count: 1
                },
                renderer: dateAxisRenderer
            }));

            dateAxis.set("tooltip", am5.Tooltip.new(root, {
                themeTags: ["axis"]
            }));


            // Add series
            // https://www.amcharts.com/docs/v5/charts/xy-chart/series/
            var valueSeries1 = chart.series.push(am5xy.LineSeries.new(root, {
                name: "XTD",
                valueYField: "price1",
                calculateAggregates: true,
                valueYShow: "valueYChangeSelectionPercent",
                valueXField: "date",
                xAxis: dateAxis,
                yAxis: valueAxis,
                legendValueText: "{valueY}"
            }));

            var valueSeries2 = chart.series.push(am5xy.LineSeries.new(root, {
                name: "BTD",
                valueYField: "price2",
                calculateAggregates: true,
                valueYShow: "valueYChangeSelectionPercent",
                valueXField: "date",
                xAxis: dateAxis,
                yAxis: valueAxis,
                legendValueText: "{valueY}"
            }));


            // Add series tooltips
            // https://www.amcharts.com/docs/v5/charts/xy-chart/series/#Tooltips
            var valueTooltip = valueSeries1.set("tooltip", am5.Tooltip.new(root, {
                getFillFromSprite: false,
                getStrokeFromSprite: true,
                getLabelFillFromSprite: true,
                autoTextColor: false,
                pointerOrientation: "horizontal",
                labelText: "{name}: {valueY} {valueYChangePercent.formatNumber('[#00ff00]+#,###.##|[#ff0000]#,###.##|[#999999]0')}%"
            }));
            valueTooltip.get("background").set("fill", bgColor);

            var valueTooltip2 = valueSeries2.set("tooltip", am5.Tooltip.new(root, {
                getFillFromSprite: false,
                getStrokeFromSprite: true,
                getLabelFillFromSprite: true,
                autoTextColor: false,
                pointerOrientation: "horizontal",
                labelText: "{name}: {valueY} {valueYChangePercent.formatNumber('[#00ff00]+#,###.##|[#ff0000]#,###.##|[#999999]0')}%"
            }));
            valueTooltip2.get("background").set("fill", bgColor);

            var firstColor = chart.get("colors").getIndex(0);
            var volumeSeries = chart.series.push(am5xy.ColumnSeries.new(root, {
                name: "XTD",
                fill: firstColor,
                stroke: firstColor,
                valueYField: "quantity",
                valueXField: "date",
                valueYGrouped: "sum",
                xAxis: dateAxis,
                yAxis: volumeAxis,
                legendValueText: "{valueY}",
                tooltip: am5.Tooltip.new(root, {
                    labelText: "{valueY}"
                })
            }));
            volumeSeries.columns.template.setAll({
                width: am5.percent(40),
                strokeWidth: 0.2,
                strokeOpacity: 1,
                stroke: am5.color(0xffffff)
            });


            // Add legend to axis header
            // https://www.amcharts.com/docs/v5/charts/xy-chart/axes/axis-headers/
            // https://www.amcharts.com/docs/v5/charts/xy-chart/legend-xy-series/
            var legend = chart.plotContainer.children.push(am5.Legend.new(root, {
                useDefaultMarker: true
            }));
            legend.labels.template.setAll({
                fill: bodyColor
            });

            legend.valueLabels.template.setAll({
                fill: bodyColor
            });

            legend.data.setAll([valueSeries1, valueSeries2]);


            // Add cursor
            // https://www.amcharts.com/docs/v5/charts/xy-chart/cursor/
            chart.set("cursor", am5xy.XYCursor.new(root, {}))


            // Add scrollbar
            // https://www.amcharts.com/docs/v5/charts/xy-chart/scrollbars/
            var scrollbar = chart.set("scrollbarX", am5xy.XYChartScrollbar.new(root, {
                orientation: "horizontal",
                height: 50
            }));

            var sbDateAxis = scrollbar.chart.xAxes.push(am5xy.DateAxis.new(root, {
                groupData: true,
                groupIntervals: [{
                    timeUnit: "week",
                    count: 1
                }],
                baseInterval: {
                    timeUnit: "day",
                    count: 1
                },
                renderer: am5xy.AxisRendererX.new(root, {})
            }));

            var sbValueAxis = scrollbar.chart.yAxes.push(am5xy.ValueAxis.new(root, {
                renderer: am5xy.AxisRendererY.new(root, {})
            }));

            var sbSeries = scrollbar.chart.series.push(am5xy.LineSeries.new(root, {
                valueYField: "price1",
                valueXField: "date",
                xAxis: sbDateAxis,
                yAxis: sbValueAxis
            }));

            sbSeries.fills.template.setAll({
                visible: true,
                fillOpacity: 0.3
            });


            // Generate random data and set on series
            // https://www.amcharts.com/docs/v5/charts/xy-chart/series/#Setting_data
            var data = [];
            var price1 = 1000;
            var price2 = 2000;
            var quantity = 10000;

            for (var i = 1; i < 5000; i++) {
                price1 += Math.round((Math.random() < 0.5 ? 1 : -1) * Math.random() * 20);
                price2 += Math.round((Math.random() < 0.5 ? 1 : -1) * Math.random() * 20);

                if (price1 < 100) {
                    price1 = 100;
                }

                if (price2 < 100) {
                    price2 = 100;
                }

                quantity += Math.round((Math.random() < 0.5 ? 1 : -1) * Math.random() * 500);

                if (quantity < 0) {
                    quantity *= -1;
                }
                data.push({
                    date: new Date(2010, 0, i).getTime(),
                    price1: price1,
                    price2: price2,
                    quantity: quantity
                });
            }

            valueSeries1.data.setAll(data);
            valueSeries2.data.setAll(data);

            volumeSeries.data.setAll(data);
            sbSeries.data.setAll(data);


            // Make stuff animate on load
            // https://www.amcharts.com/docs/v5/concepts/animations/
            chart.appear(1000, 100);

        }); // end am5.ready()
    }

    var _demo3 = function () {
        // Init AmChart -- for more info, please visit the official documentiation: https://www.amcharts.com/docs/v5/getting-started/
        am5.ready(function () {

            // Create root element
            // https://www.amcharts.com/docs/v5/getting-started/#Root_element
            var root = am5.Root.new("kt_amcharts_3");


            // Set themes
            // https://www.amcharts.com/docs/v5/concepts/themes/
            root.setThemes([
                am5themes_Animated.new(root)
            ]);


            // Create chart
            // https://www.amcharts.com/docs/v5/charts/xy-chart/
            var chart = root.container.children.push(am5xy.XYChart.new(root, {
                panX: true,
                panY: false,
                wheelX: "panX",
                wheelY: "zoomX",
                layout: root.verticalLayout
            }));

            chart.get("colors").set("step", 2);


            // Create axes
            // https://www.amcharts.com/docs/v5/charts/xy-chart/axes/
            var valueAxisRenderer = am5xy.AxisRendererY.new(root, {
                inside: true
            });
            valueAxisRenderer.labels.template.setAll({
                centerY: am5.percent(100),
                maxPosition: 0.98,
                fill: bodyColor
            });
            var valueAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
                renderer: valueAxisRenderer,
                height: am5.percent(70)
            }));
            valueAxis.axisHeader.children.push(am5.Label.new(root, {
                text: "Value",
                fontWeight: "bold",
                paddingBottom: 5,
                paddingTop: 5
            }));

            var volumeAxisRenderer = am5xy.AxisRendererY.new(root, {
                inside: true
            });
            volumeAxisRenderer.labels.template.setAll({
                centerY: am5.percent(100),
                maxPosition: 0.98,
                fill: bodyColor
            });
            var volumeAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
                renderer: volumeAxisRenderer,
                height: am5.percent(30),
                layer: 5,
                numberFormat: "#a"
            }));
            volumeAxis.axisHeader.set("paddingTop", 10);
            volumeAxis.axisHeader.children.push(am5.Label.new(root, {
                text: "Volume",
                fontWeight: "bold",
                paddingTop: 5,
                paddingBottom: 5
            }));


            var dateAxisRenderer = am5xy.AxisRendererX.new(root, {});
            dateAxisRenderer.labels.template.setAll({
                minPosition: 0.01,
                maxPosition: 0.99,
                minGridDistance: 40,
                fill: bodyColor
            });
            var dateAxis = chart.xAxes.push(am5xy.DateAxis.new(root, {
                groupData: true,
                //groupCount: 20,
                baseInterval: { timeUnit: "day", count: 1 },
                renderer: dateAxisRenderer
            }));
            dateAxis.set("tooltip", am5.Tooltip.new(root, {}));

            var color = bgColor;

            // Add series
            // https://www.amcharts.com/docs/v5/charts/xy-chart/series/
            var valueSeries = chart.series.push(
                am5xy.CandlestickSeries.new(root, {
                    fill: color,
                    clustered: false,
                    calculateAggregates: true,
                    stroke: color,
                    name: "MSFT",
                    xAxis: dateAxis,
                    yAxis: valueAxis,
                    valueYField: "Close",
                    openValueYField: "Open",
                    lowValueYField: "Low",
                    highValueYField: "High",
                    valueXField: "Date",
                    lowValueYGrouped: "low",
                    highValueYGrouped: "high",
                    openValueYGrouped: "open",
                    valueYGrouped: "close",
                    legendValueText: "open: {openValueY} low: {lowValueY} high: {highValueY} close: {valueY}",
                    legendRangeValueText: "{valueYClose}"
                })
            );

            var valueTooltip = valueSeries.set("tooltip", am5.Tooltip.new(root, {
                getFillFromSprite: false,
                getStrokeFromSprite: true,
                getLabelFillFromSprite: true,
                autoTextColor: false,
                pointerOrientation: "horizontal",
                labelText: "{name}: {valueY} {valueYChangePreviousPercent.formatNumber('[#00ff00]+#,###.##|[#ff0000]#,###.##|[#999999]0')}%"
            }));
            valueTooltip.get("background").set("fill", bgColor);


            var firstColor = chart.get("colors").getIndex(0);
            var volumeSeries = chart.series.push(am5xy.ColumnSeries.new(root, {
                name: "MSFT",
                clustered: false,
                fill: firstColor,
                stroke: firstColor,
                valueYField: "Volume",
                valueXField: "Date",
                valueYGrouped: "sum",
                xAxis: dateAxis,
                yAxis: volumeAxis,
                legendValueText: "{valueY}",
                tooltip: am5.Tooltip.new(root, {
                    labelText: "{valueY}"
                })
            }));

            volumeSeries.columns.template.setAll({
                //strokeWidth: 0.5,
                //strokeOpacity: 1,
                //stroke: am5.color(0xffffff)
            });


            // Add legend to axis header
            // https://www.amcharts.com/docs/v5/charts/xy-chart/axes/axis-headers/
            // https://www.amcharts.com/docs/v5/charts/xy-chart/legend-xy-series/
            var valueLegend = valueAxis.axisHeader.children.push(
                am5.Legend.new(root, {
                    useDefaultMarker: true
                })
            );
            valueLegend.data.setAll([valueSeries]);

            var volumeLegend = volumeAxis.axisHeader.children.push(
                am5.Legend.new(root, {
                    useDefaultMarker: true
                })
            );
            volumeLegend.data.setAll([volumeSeries]);


            // Stack axes vertically
            // https://www.amcharts.com/docs/v5/charts/xy-chart/axes/#Stacked_axes
            chart.leftAxesContainer.set("layout", root.verticalLayout);


            // Add cursor
            // https://www.amcharts.com/docs/v5/charts/xy-chart/cursor/
            chart.set("cursor", am5xy.XYCursor.new(root, {}))


            // Add scrollbar
            // https://www.amcharts.com/docs/v5/charts/xy-chart/scrollbars/
            var scrollbar = chart.set("scrollbarX", am5xy.XYChartScrollbar.new(root, {
                orientation: "horizontal",
                height: 50
            }));

            var sbDateAxis = scrollbar.chart.xAxes.push(am5xy.DateAxis.new(root, {
                groupData: true,
                groupIntervals: [{
                    timeUnit: "week",
                    count: 1
                }],
                baseInterval: {
                    timeUnit: "day",
                    count: 1
                },
                renderer: am5xy.AxisRendererX.new(root, {})
            }));

            var sbValueAxis = scrollbar.chart.yAxes.push(
                am5xy.ValueAxis.new(root, {
                    renderer: am5xy.AxisRendererY.new(root, {})
                })
            );

            var sbSeries = scrollbar.chart.series.push(am5xy.LineSeries.new(root, {
                valueYField: "Adj Close",
                valueXField: "Date",
                xAxis: sbDateAxis,
                yAxis: sbValueAxis
            }));

            sbSeries.fills.template.setAll({
                visible: true,
                fillOpacity: 0.3
            });


            // Load external data
            // https://www.amcharts.com/docs/v5/charts/xy-chart/series/#Setting_data
            am5.net.load("https://www.amcharts.com/wp-content/uploads/assets/stock/MSFT.csv").then(function (result) {

                // Parse loaded data
                var data = am5.CSVParser.parse(result.response, {
                    delimiter: ",",
                    reverse: true,
                    skipEmpty: true,
                    useColumnNames: true
                });

                // Process data (convert dates and values)
                var processor = am5.DataProcessor.new(root, {
                    dateFields: ["Date"],
                    dateFormat: "yyyy-MM-dd",
                    numericFields: ["Open", "High", "Low", "Close", "Adj Close", "Volume"]
                });
                processor.processMany(data);

                console.log(data)

                // Set data
                valueSeries.data.setAll(data);
                volumeSeries.data.setAll(data);
                sbSeries.data.setAll(data);
            });



            // Make stuff animate on load
            // https://www.amcharts.com/docs/v5/concepts/animations/
            chart.appear(1000, 100);

        }); // end am5.ready()
    }

    return {
        // Public Functions
        init: function () {
            _demo1();
            _demo2();
            _demo3();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function () {
    KTGeneralAmChartsStock.init();
});
/******/ })()
;
//# sourceMappingURL=stock-charts.js.map