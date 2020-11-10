define(function(require) {
    var tep = require("text!./pie.html");
    var css = require("text!./pie.css");
    addCss(css, 'productiveState-pie');
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
            num: {
                type: [String, Number],
                default: 0
            },
            id: {
                type: [String, Number],
                default: 0
            },
            colors: {
                type: Array,
                default: function() {
                    return ['#3c90f7', '#eeeeee', '#5ebe67'];
                }
            },
            width: {
                type: [String, Number],
                default: 0
            },
            height: {
                type: [String, Number],
                default: 0
            },

        },
        computed: {
            barId: function() {
                return 'bar' + this.id + this.randomString(4);
            },
            gaugeId: function() {
                return 'gauge' + this.id + this.randomString(4);
            },
            pieId: function() {
                return 'pieId' + this.id + this.randomString(4);
            },
            opt: function() {},
            CssStyle: function() {
                return {
                    width: this.widthPX,
                    height: this.heightPX,
                }
            },
        },
        data: function() {
            return {
                widthPX: '100px',
                heightPX: '100px',
            };
        },
        mounted: function() {
            this.$nextTick(function() {
                this.one();
            });
        },
        updated: function() {
            this.$nextTick(function() {
                this.one();
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
            one: function() {
                this.heightPX = this.width || this.height || this.heightPX;
                this.widthPX = this.width || this.widthPX;
                console.log('this.CssStyle', this.CssStyle, this.widthPX, this.heightPX);

                var num = Number(this.num) || 0;
                var data = [
                    { value: num, name: '北京' },
                    { value: 100 - num, name: '广东' },
                ];
                this.pie(data);
            },
            // 饼图
            pie: function(data) {
                var that = this;
                console.log('pie', that.colors);
                // echarts.init(document.getElementById(document.getElementById(this.pieId))).dispose();
                var chartPie = echarts.init(document.getElementById(this.pieId), '', {
                    width: that.widthPX,
                    height: that.widthPX,
                });
                optionPies = {
                    title: {
                        left: 'center'
                    },
                    tooltip: {
                        show: false
                    },
                    /* tooltip: {
                        trigger: 'item',
                        formatter: '{b} <br/> {d}%',
                        fontSize: '12px',
                        backgroundColor: 'rgba(255,255,255,.95)',
                        textStyle: {
                            color: '#333333'
                        },
                        padding: [5, 10],
                    },*/
                    legend: {
                        orient: 'vertical',
                        left: 'left',
                        data: ['DownTime', 'Running', 'pause']
                    },
                    color: that.colors,
                    // tooltip: {
                    //     trigger: 'item',
                    //     formatter: '{a} <br/>{b} : {c} ({d}%)'
                    // },
                    // legend: {
                    //     orient: 'vertical',
                    //     left: 'left',
                    //     data: ['DownTime', 'Running', 'pause']
                    // },
                    series: [{
                        label: {
                            normal: {
                                position: 'inner',
                                show: false
                            }
                        },
                        type: 'pie',
                        radius: '55%',
                        center: ['50%', '50%'],
                        data: data,
                        emphasis: {
                            // itemStyle: {
                            //     shadowBlur: 10,
                            //     shadowOffsetX: 0,
                            //     shadowColor: 'rgba(0, 0, 0, 0.5)'
                            // }
                        },
                        hoverAnimation: false

                    }]
                };
                chartPie.setOption(optionPies);
            },
        },
    };
    return paths;
});