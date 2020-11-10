define(function(require) {
    var tep = require("text!./item.html");
    var css = require("text!./item.css");
    addCss(css, 'productiveState-edit');
    var _this = null;
    var paths = {
        template: tep,
        props: {
            obj: {
                type: Object,
                default: function() {
                    return {};
                }
            },
            id: {
                type: [String, Number],
                default: Math.random()
            }
        },
        computed: {
            barId: function() {
                return 'bar' + this.id + this.randomString(4);
            },
            gaugeId: function() {
                return 'gauge' + this.id + this.randomString(4);
            },
            opt: function() {},
        },
        mounted: function() {
            this.$nextTick(function() {
                this.init();
            });
        },
        methods: {
            randomString: function(n) {
                var chars = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
                var res = "";
                for (var i = 0; i < n; i++) {
                    var id = Math.ceil(Math.random() * 35);
                    res += chars[id];
                }
                return res;
            },
            gauge0: function(name, data) {
                return {
                    tooltip: {
                        formatter: '{a} <br/>{b} : {c}%'
                    },
                    series: [{
                        name: name,
                        type: 'gauge',
                        detail: { formatter: '{value}%' },
                        data: data
                    }]
                };
            },
            gauge1: function(option) {
                var d = option.data[0];
                console.log('option', d)
                var v = Number(String(d.value).replace('%', ''));
                return this.gauge0(option.name, [{
                    value: v,
                    name: d.label,
                }]);
            },
            bar0: function(name, xAxis, series, colors) {
                var option = {
                    tooltip: {
                        formatter: '{a} <br/>{b} : {c}%'
                    },
                    xAxis: {
                        type: 'category',
                        data: xAxis
                    },
                    yAxis: {
                        type: 'value',
                        min: 0,
                        max: 100,
                    },
                    series: [{
                        name: name,
                        data: series,
                        type: 'bar',
                        showBackground: false,
                        backgroundStyle: {
                            color: 'rgba(220, 220, 220, 0.8)'
                        },

                        itemStyle: {
                            normal: {
                                color: function(params) {
                                    var rand = "#" + Math.floor(Math.random() * (256 * 256 * 256 - 1)).toString(16);
                                    var colorList = colors;
                                    return colorList[params.dataIndex] || rand;
                                }
                            }
                        }
                    }]
                };
                return option;
            },
            bar1: function(option) {
                var datas = option.data.slice(0, 5);
                var xAxis = datas.map(function(d) {
                    return d.label;
                });
                var series = datas.map(function(d) {
                    return Number(String(d.value).replace('%', ''));
                });
                var colors = datas.map(function(d) {
                    return d.color;
                });
                return this.bar0(option.name, xAxis, series, colors);
            },
            init: function() {
                var Bar1 = echarts.init(document.getElementById(this.barId));
                Bar1.setOption(this.bar1(this.obj), true);
                var Gau1 = echarts.init(document.getElementById(this.gaugeId));
                Gau1.setOption(this.gauge1(this.obj), true);
            }
        },
    };
    return paths;
});