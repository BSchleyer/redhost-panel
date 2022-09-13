/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!********************************************************************!*\
  !*** ../demo8/src/js/custom/documentation/charts/amcharts/maps.js ***!
  \********************************************************************/


// Class definition
var KTGeneralAmChartsMaps = function () {
    // Shared variables
    var chart;
    const bodyColor = getComputedStyle(document.documentElement).getPropertyValue('--bs-body-color');
    const bgColor = getComputedStyle(document.documentElement).getPropertyValue('--bs-body-bg');

    // Private functions
    var demo1 = function () {
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

            // Create the map chart
            // https://www.amcharts.com/docs/v5/charts/map-chart/
            var chart = root.container.children.push(am5map.MapChart.new(root, {
                panX: "translateX",
                panY: "translateY",
                projection: am5map.geoMercator()
            }));

            // Create main polygon series for countries
            // https://www.amcharts.com/docs/v5/charts/map-chart/map-polygon-series/
            var polygonSeries = chart.series.push(am5map.MapPolygonSeries.new(root, {
                geoJSON: am5geodata_worldLow,
                exclude: ["AQ"]
            }));

            polygonSeries.mapPolygons.template.setAll({
                tooltipText: "{name}",
                toggleKey: "active",
                interactive: true
            });

            polygonSeries.mapPolygons.template.states.create("hover", {
                fill: root.interfaceColors.get("primaryButtonHover")
            });

            polygonSeries.mapPolygons.template.states.create("active", {
                fill: root.interfaceColors.get("primaryButtonHover")
            });

            // US Series
            // Create main polygon series for countries
            // https://www.amcharts.com/docs/v5/charts/map-chart/map-polygon-series/
            var polygonSeriesUS = chart.series.push(am5map.MapPolygonSeries.new(root, {
                geoJSON: am5geodata_usaLow
            }));

            polygonSeriesUS.mapPolygons.template.setAll({
                tooltipText: "{name}",
                toggleKey: "active",
                interactive: true
            });

            var colors = am5.ColorSet.new(root, {});

            polygonSeriesUS.mapPolygons.template.set("fill", colors.getIndex(3));

            polygonSeriesUS.mapPolygons.template.states.create("hover", {
                fill: root.interfaceColors.get("primaryButtonHover")
            });

            polygonSeriesUS.mapPolygons.template.states.create("active", {
                fill: root.interfaceColors.get("primaryButtonHover")
            });

            // Add zoom control
            // https://www.amcharts.com/docs/v5/charts/map-chart/map-pan-zoom/#Zoom_control
            chart.set("zoomControl", am5map.ZoomControl.new(root, {}));

            // Set clicking on "water" to zoom out
            chart.chartContainer.get("background").events.on("click", function () {
                chart.goHome();
            })

            // Make stuff animate on load
            chart.appear(1000, 100);
        }); // end am5.ready()
    }

    var demo2 = function () {
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

            // Create the map chart
            // https://www.amcharts.com/docs/v5/charts/map-chart/
            var chart = root.container.children.push(am5map.MapChart.new(root, {
                panX: "rotateX",
                panY: "translateY",
                projection: am5map.geoMercator(),
                homeGeoPoint: { latitude: 2, longitude: 2 }
            }));

            var cont = chart.children.push(am5.Container.new(root, {
                layout: root.horizontalLayout,
                x: 20,
                y: 40
            }));

            // Add labels and controls
            cont.children.push(am5.Label.new(root, {
                centerY: am5.p50,
                text: "Map",
                fill: bodyColor
            }));

            var switchButton = cont.children.push(am5.Button.new(root, {
                themeTags: ["switch"],
                centerY: am5.p50,
                icon: am5.Circle.new(root, {
                    themeTags: ["icon"]
                })
            }));

            switchButton.on("active", function () {
                if (!switchButton.get("active")) {
                    chart.set("projection", am5map.geoMercator());
                    chart.set("panY", "translateY");
                    chart.set("rotationY", 0);
                    backgroundSeries.mapPolygons.template.set("fillOpacity", 0);
                } else {
                    chart.set("projection", am5map.geoOrthographic());
                    chart.set("panY", "rotateY")

                    backgroundSeries.mapPolygons.template.set("fillOpacity", 0.1);
                }
            });

            cont.children.push(
                am5.Label.new(root, {
                    centerY: am5.p50,
                    text: "Globe",
                    fill: bodyColor
                })
            );

            // Create series for background fill
            // https://www.amcharts.com/docs/v5/charts/map-chart/map-polygon-series/#Background_polygon
            var backgroundSeries = chart.series.push(am5map.MapPolygonSeries.new(root, {}));
            backgroundSeries.mapPolygons.template.setAll({
                fill: root.interfaceColors.get("alternativeBackground"),
                fillOpacity: 0,
                strokeOpacity: 0
            });

            // Add background polygon
            // https://www.amcharts.com/docs/v5/charts/map-chart/map-polygon-series/#Background_polygon
            backgroundSeries.data.push({
                geometry: am5map.getGeoRectangle(90, 180, -90, -180)
            });

            // Create main polygon series for countries
            // https://www.amcharts.com/docs/v5/charts/map-chart/map-polygon-series/
            var polygonSeries = chart.series.push(am5map.MapPolygonSeries.new(root, {
                geoJSON: am5geodata_worldLow
            }));

            // Create line series for trajectory lines
            // https://www.amcharts.com/docs/v5/charts/map-chart/map-line-series/
            var lineSeries = chart.series.push(am5map.MapLineSeries.new(root, {}));
            lineSeries.mapLines.template.setAll({
                stroke: bodyColor,
                strokeOpacity: 0.3
            });

            // Create point series for markers
            // https://www.amcharts.com/docs/v5/charts/map-chart/map-point-series/
            var pointSeries = chart.series.push(am5map.MapPointSeries.new(root, {}));

            pointSeries.bullets.push(function () {
                var circle = am5.Circle.new(root, {
                    radius: 7,
                    tooltipText: "Drag me!",
                    cursorOverStyle: "pointer",
                    tooltipY: 0,
                    fill: am5.color(0xffba00),
                    stroke: bgColor,
                    strokeWidth: 2,
                    draggable: true
                });

                circle.events.on("dragged", function (event) {
                    var dataItem = event.target.dataItem;
                    var projection = chart.get("projection");
                    var geoPoint = chart.invert({ x: circle.x(), y: circle.y() });

                    dataItem.setAll({
                        longitude: geoPoint.longitude,
                        latitude: geoPoint.latitude
                    });
                });

                return am5.Bullet.new(root, {
                    sprite: circle
                });
            });

            var paris = addCity({ latitude: 48.8567, longitude: 2.351 }, "Paris");
            var toronto = addCity({ latitude: 43.8163, longitude: -79.4287 }, "Toronto");
            var la = addCity({ latitude: 34.3, longitude: -118.15 }, "Los Angeles");
            var havana = addCity({ latitude: 23, longitude: -82 }, "Havana");

            var lineDataItem = lineSeries.pushDataItem({
                pointsToConnect: [paris, toronto, la, havana]
            });

            var planeSeries = chart.series.push(am5map.MapPointSeries.new(root, {}));

            var plane = am5.Graphics.new(root, {
                svgPath:
                    "m2,106h28l24,30h72l-44,-133h35l80,132h98c21,0 21,34 0,34l-98,0 -80,134h-35l43,-133h-71l-24,30h-28l15,-47",
                scale: 0.06,
                centerY: am5.p50,
                centerX: am5.p50,
                fill: bgColor
            });

            planeSeries.bullets.push(function () {
                var container = am5.Container.new(root, {});
                container.children.push(plane);
                return am5.Bullet.new(root, { sprite: container });
            });

            var planeDataItem = planeSeries.pushDataItem({
                lineDataItem: lineDataItem,
                positionOnLine: 0,
                autoRotate: true
            });

            planeDataItem.animate({
                key: "positionOnLine",
                to: 1,
                duration: 10000,
                loops: Infinity,
                easing: am5.ease.yoyo(am5.ease.linear)
            });

            planeDataItem.on("positionOnLine", function (value) {
                if (value >= 0.99) {
                    plane.set("rotation", 180);
                } else if (value <= 0.01) {
                    plane.set("rotation", 0);
                }
            });

            function addCity(coords, title) {
                return pointSeries.pushDataItem({
                    latitude: coords.latitude,
                    longitude: coords.longitude
                });
            }

            // Make stuff animate on load
            chart.appear(1000, 100);

        }); // end am5.ready()
    }

    var demo3 = function () {
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


            // Create the map chart
            // https://www.amcharts.com/docs/v5/charts/map-chart/
            var chart = root.container.children.push(am5map.MapChart.new(root, {
                panX: "rotateX",
                panY: "rotateY",
                projection: am5map.geoOrthographic(),
                paddingBottom: 20,
                paddingTop: 20,
                paddingLeft: 20,
                paddingRight: 20
            }));


            // Create main polygon series for countries
            // https://www.amcharts.com/docs/v5/charts/map-chart/map-polygon-series/
            var polygonSeries = chart.series.push(am5map.MapPolygonSeries.new(root, {
                geoJSON: am5geodata_worldLow
            }));

            polygonSeries.mapPolygons.template.setAll({
                tooltipText: "{name}",
                toggleKey: "active",
                interactive: true
            });

            polygonSeries.mapPolygons.template.states.create("hover", {
                fill: root.interfaceColors.get("primaryButtonHover")
            });


            // Create series for background fill
            // https://www.amcharts.com/docs/v5/charts/map-chart/map-polygon-series/#Background_polygon
            var backgroundSeries = chart.series.push(am5map.MapPolygonSeries.new(root, {}));
            backgroundSeries.mapPolygons.template.setAll({
                fill: root.interfaceColors.get("alternativeBackground"),
                fillOpacity: 0.1,
                strokeOpacity: 0
            });
            backgroundSeries.data.push({
                geometry: am5map.getGeoRectangle(90, 180, -90, -180)
            });


            // Create graticule series
            // https://www.amcharts.com/docs/v5/charts/map-chart/graticule-series/
            var graticuleSeries = chart.series.push(am5map.GraticuleSeries.new(root, {}));
            graticuleSeries.mapLines.template.setAll({ strokeOpacity: 0.1, stroke: root.interfaceColors.get("alternativeBackground") })


            // Rotate animation
            chart.animate({
                key: "rotationX",
                from: 0,
                to: 360,
                duration: 30000,
                loops: Infinity
            });


            // Make stuff animate on load
            chart.appear(1000, 100);

        }); // end am5.ready()
    }

    var demo4 = function () {
        // Init AmChart -- for more info, please visit the official documentiation: https://www.amcharts.com/docs/v5/getting-started/
        am5.ready(function () {

            // Create root element
            // https://www.amcharts.com/docs/v5/getting-started/#Root_element
            var root = am5.Root.new("kt_amcharts_4");

            // Set themes
            // https://www.amcharts.com/docs/v5/concepts/themes/
            root.setThemes([
                am5themes_Animated.new(root)
            ]);

            // Create the map chart
            // https://www.amcharts.com/docs/v5/charts/map-chart/
            var chart = root.container.children.push(
                am5map.MapChart.new(root, {
                    panX: "translateX",
                    panY: "translateY",
                    projection: am5map.geoMercator()
                })
            );

            var colorSet = am5.ColorSet.new(root, {});

            // Create main polygon series for time zone areas
            // https://www.amcharts.com/docs/v5/charts/map-chart/map-polygon-series/
            var areaSeries = chart.series.push(
                am5map.MapPolygonSeries.new(root, {
                    geoJSON: am5geodata_worldTimeZoneAreasLow
                })
            );

            var areaPolygonTemplate = areaSeries.mapPolygons.template;
            areaPolygonTemplate.setAll({ fillOpacity: 0.6 });
            areaPolygonTemplate.adapters.add("fill", function (fill, target) {
                return am5.Color.saturate(
                    colorSet.getIndex(areaSeries.mapPolygons.indexOf(target)),
                    0.5
                );
            });

            areaPolygonTemplate.states.create("hover", { fillOpacity: 0.8 });

            // Create main polygon series for time zones
            // https://www.amcharts.com/docs/v5/charts/map-chart/map-polygon-series/
            var zoneSeries = chart.series.push(
                am5map.MapPolygonSeries.new(root, {
                    geoJSON: am5geodata_worldTimeZonesLow
                })
            );

            zoneSeries.mapPolygons.template.setAll({
                fill: am5.color(0x000000),
                fillOpacity: 0.08
            });

            var zonePolygonTemplate = zoneSeries.mapPolygons.template;
            zonePolygonTemplate.setAll({ interactive: true, tooltipText: "{id}" });
            zonePolygonTemplate.states.create("hover", { fillOpacity: 0.3 });

            // labels
            var labelSeries = chart.series.push(am5map.MapPointSeries.new(root, {}));
            labelSeries.bullets.push(() => {
                return am5.Bullet.new(root, {
                    sprite: am5.Label.new(root, {
                        text: "{id}",
                        populateText: true,
                        centerX: am5.p50,
                        centerY: am5.p50,
                        fontSize: "0.7em",
                        fill: bodyColor
                    })
                });
            });

            // create labels for each zone
            zoneSeries.events.on("datavalidated", () => {
                am5.array.each(zoneSeries.dataItems, (dataItem) => {
                    var centroid = dataItem.get("mapPolygon").visualCentroid();
                    labelSeries.pushDataItem({
                        id: dataItem.get("id"),
                        geometry: {
                            type: "Point",
                            coordinates: [centroid.longitude, centroid.latitude]
                        }
                    });
                });
            });

            // Add zoom control
            // https://www.amcharts.com/docs/v5/charts/map-chart/map-pan-zoom/#Zoom_control
            chart.set("zoomControl", am5map.ZoomControl.new(root, {}));

            // Add labels and controls
            var cont = chart.children.push(
                am5.Container.new(root, {
                    layout: root.horizontalLayout,
                    x: 20,
                    y: 40
                })
            );

            cont.children.push(
                am5.Label.new(root, {
                    centerY: am5.p50,
                    text: "Map",
                    fill: bodyColor
                })
            );

            var switchButton = cont.children.push(
                am5.Button.new(root, {
                    themeTags: ["switch"],
                    centerY: am5.p50,
                    icon: am5.Circle.new(root, {
                        themeTags: ["icon"]
                    })
                })
            );

            switchButton.on("active", function () {
                if (!switchButton.get("active")) {
                    chart.set("projection", am5map.geoMercator());
                    chart.set("panX", "translateX");
                    chart.set("panY", "translateY");
                } else {
                    chart.set("projection", am5map.geoOrthographic());
                    chart.set("panX", "rotateX");
                    chart.set("panY", "rotateY");
                }
            });

            cont.children.push(
                am5.Label.new(root, {
                    centerY: am5.p50,
                    text: "Globe",
                    fill: bodyColor
                })
            );
            // Make stuff animate on load
            chart.appear(1000, 100);

        }); // end am5.ready()
    }

    return {
        // Public Functions
        init: function () {
            demo1();
            demo2();
            demo3();
            demo4();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function () {
    KTGeneralAmChartsMaps.init();
});
/******/ })()
;
//# sourceMappingURL=maps.js.map