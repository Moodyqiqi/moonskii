define(function(require) {
    var tep = require("text!./echart.html");
    var css = require("text!./echart.css");
    addCss(css, 'g_echart');
    var _this = null
    var paths = {
        template: tep,
        components: {
            'product-show': require("./components/item")
        },
        data: function() {
            return {
                ps: [],
                title: null,
                title2: null,
                list: [
                    {},
                    {},
                    {},
                    {},
                ],
                opt: [],
            }
        },
        computed: {
            opt2: function() {
                return this.rd4();
            },
        },
        mounted: function() {
            this.$nextTick(function() {
                var ps = this.rep()
                if (!ps) {
                    this.$Message.error('参数未传递');
                    console.log('ps', ps)
                }
                /*
                 else {
                    var ps = Qs.parse(this.rep());
                    var obj = JSON.parse(ps.ps)
                    if (obj) {
                        this.list = obj.list
                        this.title = obj.title
                        this.title2 = obj.title2
                    }
                } */
                this.one()

                /*
                var ps = Qs.parse(this.rep());
                var obj = JSON.parse(ps.ps)
                if (obj) {
                    this.list = obj.list
                    this.title = obj.title
                    this.title2 = obj.title2
                }
                this.one()
                */
            })
        },
        updated: function() {
            this.$nextTick(function() {
                // this.one();
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
            rep: function() {
                var url = window.location.search;
                if (!url) {
                    url = window.location.hash;
                    if (url) {
                        while (url[0] !== '?' && url) {
                            url = url.slice(1)
                        }
                    }
                }
                if (url) {
                    while (url[0] == '?' && url) {
                        url = url.slice(1)
                    }
                }
                return url;
            },

            one: function() {
                var ps = this.rep();
                if (ps) {
                    var ps = Qs.parse(ps);
                    if (ps) {
                        console.log('ps', ps)
                        var obj = JSON.parse(ps.ps);
                        this.list = obj.list;
                        this.title = obj.title;
                        this.title2 = obj.title2;
                        var rd = this.rd4();
                        console.log(rd, this.opt)
                    }
                }

                this.opt = this.rd4();
            },
            rd3: function(option) {
                var arr = option.chartValues || []
                return {
                    name: option.name,
                    data: [{
                            label: '运行',
                            color: '#52a73e',
                            value: (arr[0] || 0) + '%',
                        },
                        {
                            label: 'Warnung',
                            color: '#ff8500',
                            value: (arr[1] || 0) + '%',
                        },
                        {
                            label: '故障',
                            color: '#d12027',
                            value: (arr[2] || 0) + '%',
                        },
                        {
                            label: '连接错误',
                            color: '#3d008e',
                            value: (arr[3] || 0) + '%',
                        },
                        {
                            label: '连接错误',
                            color: '#c0c0c0',
                            value: (arr[4] || 0) + '%',
                        },
                        {
                            label: '实际周期（秒）',
                            value: (option.value1 || 0).toFixed(1),
                        },
                        {
                            label: '计划周期（秒）',
                            value: (option.value2 || 0).toFixed(1),
                        },
                    ]
                };
            },
            rd4: function() {
                var opt = this.list.map(d => {
                    return this.rd3(d)
                })
                return opt;
            },

            one2: function() {
                var myChart = echarts.init(document.getElementById('one'));

                var option = {
                    color: ['red', 'blue'],
                    xAxis: {
                        type: 'category',
                        data: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
                    },
                    grid: {
                        top: "2%",
                        left: '0%',
                        right: '0%',
                        bottom: '0%',
                        containLabel: true
                    },
                    yAxis: {
                        type: 'value'
                    },
                    series: [{
                        data: [120, 200, 150, 80, 70, 110, 130],
                        type: 'bar',
                        showBackground: true,
                        backgroundStyle: {
                            color: 'rgba(220, 220, 220, 0.8)'
                        }
                    }]
                };

                // 使用刚指定的配置项和数据显示图表。
                myChart.setOption(option);
                this.oneY()
            },
            oneY: function() {
                var myChart = echarts.init(document.getElementById('oneY'));
                option = {
                    tooltip: {
                        formatter: '{a} <br/>{b} : {c}%'
                    },
                    grid: {
                        top: "2%",
                        left: '0%',
                        right: '0%',
                        bottom: '0%',

                    },
                    toolbox: {
                        /* feature: {
                        	restore: {},
                        	saveAsImage: {}
                        } */
                    },
                    series: [{
                        name: '业务指标',
                        type: 'gauge',
                        detail: {
                            formatter: '{value}%'
                        },
                        data: [{
                            value: 50,
                            name: '完成率'
                        }]
                    }]
                };

                setInterval(function() {
                    option.series[0].data[0].value = (Math.random() * 100).toFixed(2) - 0;
                    myChart.setOption(option, true);
                }, 2000);
                myChart.setOption(option);
            }
        },
        watch: {

        },
    }
    return paths
});