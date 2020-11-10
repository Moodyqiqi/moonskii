define(function(require) {
    var tep = require("text!./jiepu.html");
    var app_index = require("text!./jiepu.css");
    addCss(app_index, 'sixiang_employee_data-list');
    var _this = this;
    var paths = {
        template: tep,
        components: {
            'chart-pie': require("./components/pie")
        },
        computed: {
            LeftStyle: function() {
                return {
                    width: "50%"
                };
            },
            CenterStyle: function() {
                return {
                    width: "40%"
                };
            },
            RightStyle: function() {
                return {
                    width: "400px"
                };
            },
            MonthDateRange: function() {
                var arr = [];
                for (var i = 1; i <= 28; i++) {
                    arr.push(i)
                }
                return arr;
            },
            /*
            currentTime: function () {
                return this.getNowFormatDate();
            }
            */
        },
        data: function() {
            return {
                FullPageStyle: {
                    backgroundColor: '#0c162f',
                    color: 'white',
                    height: '1080px',
                    padding: '20px',
                    margin: 0,
                    position: 'fixed',
                    top: '0',
                    left: '0',
                    right: '0',
                    bottom: '0',
                    'z-index': 102
                },

                model: {},
                id: this.$route.query.id,
                BASEURL: globalUrl,
                languge: $getCookie("languge") ? $getCookie("languge") : 1,
                permissions: {},

                mailModel: false,
                username: 'admin',
                password: '123456',
                popVisible: false,
                index: 0,
                nodes: [],
                userName: $getCookie("userName"),
                id: $getCookie("projectId"),
                spinShow: true,
                timeNumber: 60,
                detail: { slaveId_name: '', DownTime: null, FPY: null, OEE: null, Scrap: null },
                modal1: false,
                leftChart: { OEE: null, DownTime: null, FPY: null, Scrap: null },
                PageTotal: 0,
                selectedKeys: [],
                jsonLanguge: { // 语言json双语  
                    data1: ['Daily delivery:', '日发送:'],
                    data2: ['Daily transmitting device:', '日发送设备:'],
                    data3: ['All daily sending devices:', '日发送设备全部:'],
                    data4: ['Weekly delivery:', '周发送:'],
                    data5: ['Weekly sending device:', '周发送设备:'],
                    data6: ['All weekly sending devices:', '周发送设备全部:'],
                    data7: ['Monthly delivery:', '月发送:'],
                    data8: ['Monthly sending device:', '月发送设备:'],
                    data9: ['All monthly sending devices:', '月发送设备全部:'],
                    data10: ['Mailbox server:', '邮箱服务器:'],
                    data11: ['Email account:', '邮箱账号:'],
                    data12: ['Email password:', '邮箱密码:'],
                    data13: ['Recipient address:', '收件人地址:'],
                    data14: ['submit', '提交'],
                    data15: ['back', '返回'],
                    data16: ['Email:', '收件邮箱:'],
                    data17: ['Daily delivery:', '日常报表发送时间:'],
                    data18: ['Weekly delivery:', '周期报表发送时间:'],
                    data19: ['Monthly delivery:', '月度报表发送时间:'],
                    data20: ['All:', '全部:'],
                    data21: ['Yearly delivery:', '年度报表发送时间:'],
                    data22: ['Daily delivery:', '年发送:'],



                    time: ['second(s)', '秒'],
                    timeInner: ['Time Interval:', '时间间隔:'],
                    reFreshTitle: ['Set Refresh Time', '设置刷新时间'],
                    titleDetail: ['Details', '详情'],
                    bigTitle: ['Roche E1G1 OEE Monitoring System', 'Roche E1G1 OEE Monitoring System'], // 捷普系统
                    total: ['Total Data', '总数据'],
                    machineList: ['Comprehensive ranking of machines', '机器综合排行'],
                    mailConfig: ['Mail configuration', '邮件配置'],
                    refresh: ['Refresh', '刷新'],
                    placeDataHold: ['please select data and time', '选择日期和时间'],
                    selectMachine: ['Machine', '机器'],
                    facility: ['Facility', '设备'],
                    state: ['State', '状态'],
                    ok: ['OK', '确定'],
                    cancle: ['Cancle', '取消'],
                    account: ['Account', '用户名:'],
                    password: ['Password', '密码:'],

                    weeksArr: [
                        ['Mon', 'Tue', 'Wed', 'Thur', 'Fri', 'Sat', 'Sun'],
                        ['周一', '周二', '周三', '周四', '周五', '周六', '周日']
                    ],
                    BZ1: ['time', '时间段'],
                    BZ2: ['target', '目标'],
                    BZ3: ['actual', '实际'],
                    TZ1: ['More than one mailbox needs to end with ; ', '多个邮箱需要以 ; 结尾'],
                },
                currentTime: null, // 系统当前时间
                deviceList: [], // 设备下拉选择
                statuList: [], // 状态下拉选择
                items: null,
                allData: [], // 所有表格数据
                search: { // 左侧的搜索条件
                    // Devices: [],
                    StartTime: null,
                    EndTime: null
                },
                search1: { // 右侧的搜索条件
                    isAllData: true,
                    Devices: [],
                    State: null, //状态
                    StartTime: null, //开始时间 
                    EndTime: null, //结束时间
                    PageIndex: 0,
                    onePageCount: 15,
                },
                model11: $getCookie("languge") ? $getCookie("languge") : 1,
                model12: [],
                PageTotal: 0,
                columns: [{
                        title: '序号',
                        // type: 'index',
                        key: "index",
                        align: 'center',
                        render: (h, params) => {
                            let color = '#0b7242'
                            if (params.index == 0) {
                                color = '#656afc'
                            } else if (params.index == 1) {
                                color = '#ff6969'
                            }
                            return h('span', {
                                style: {
                                    color: 'white',
                                    width: '40px',
                                    height: '40px',
                                    background: color,
                                    padding: '10px'
                                }
                            }, params.index + 1);

                        }


                    },

                    {
                        title: 'Machine',
                        key: 'name',
                        align: 'center',
                        sortable: true,

                    },
                    {
                        title: 'OEE',
                        key: 'OEE',
                        slot: 'OEE',
                        align: 'center',
                        sortable: true,
                    },
                    {
                        title: 'DownTime',
                        key: 'DownTime',
                        align: 'center',
                        sortable: true,

                    },
                    {
                        title: 'FPY',
                        key: 'FPY',
                        slot: 'FPY',
                        align: 'center',
                        sortable: true,

                    },


                    {
                        title: "Scrap",
                        key: 'Scrap',
                        slot: 'Scrap',
                        align: 'center',
                        sortable: true,

                    },
                ],

                spinShow: true,
                items: [],
                selectedKeys: [],
                drawerFlag: false,
                editData: {
                    flag: false,
                    id: 0,
                    loading: false
                },
                isOpenWindow: false, //新窗口打开编辑页
                permissions: {},

                BZData: [
                    [],
                    []
                ], // BaozhuangData
                CenterDatePicker: ['2020-09-30', '2020-10-01'], // 中间日期选择
                LeftDatePicker: ['2020-09-30', '2020-10-01'],
                OEEColor: ['#ff6a66', '#eeeeee'],
                FPYColor: ['#5ebe67', '#eeeeee'],
                ScrapColor: ['#3c90f7', '#eeeeee'],

                emailAccount: {
                    username: 'admin',
                    password: '123456',
                },
            }
        },
        mounted: function() {
            _this = this;
            var timeNumber = $getCookie('RefreashTimeNumber');
            if (timeNumber >= 5) {
                this.timeNumber = timeNumber
            }

            this.init();
        },
        watch: {
            '$route': {
                handler: function(n, o) {
                    this.init()
                },
                deep: true
            }
        },

        beforeCreate: function() {
            var cls = document.querySelector('body').getAttribute('class');
            cls = '' + cls + ' nobar';
            document.querySelector('body').setAttribute('class', cls);
        },

        beforeDestroy: function() {
            var cls = document.querySelector('body').getAttribute('class');
            cls = cls.replace(/nobar/g, '');
            document.querySelector('body').setAttribute('class', cls);
            if (this.formatDate) {
                clearInterval(this.formatDate); // 在Vue实例销毁前，清除时间定时器
            }
        },
        methods: {
            initDateTime() {
                var date = new Date()
                var LeftStartTime, LeftEndTime, CenterStartTime, CenterEndTime;
                var StartTime = date.getFullYear() + '-' + (date.getMonth() + 1) + date.getDay(); //  + ' 00:00'
                var EndTime = date.getFullYear() + '-' + (date.getMonth() + 1) + date.getDay(); //  + ' 23:59'
                if ($getCookie('CenterStartTime')) {
                    CenterStartTime = $getCookie('CenterStartTime')
                } else {
                    CenterStartTime = this.CenterDatePicker[0] || StartTime;
                }
                if ($getCookie('CenterEndTime')) {
                    CenterEndTime = $getCookie('CenterEndTime');
                } else {
                    CenterEndTime = this.CenterDatePicker[1] || EndTime;
                }
                if ($getCookie('LeftStartTime')) {
                    LeftStartTime = $getCookie('LeftStartTime')
                } else {
                    LeftStartTime = this.LeftDatePicker[0] || StartTime;
                }
                if ($getCookie('LeftEndTime')) {
                    LeftEndTime = $getCookie('LeftEndTime');
                } else {
                    LeftEndTime = this.LeftDatePicker[1] || EndTime;
                }
                this.CenterDatePicker = [CenterStartTime, CenterEndTime];
                this.LeftDatePicker = [LeftStartTime, LeftEndTime];
                this.search1 = Object.assign({}, this.search1, {
                    StartTime: CenterStartTime,
                    EndTime: CenterEndTime,
                });
                this.search = Object.assign({}, this.search, {
                    StartTime: LeftStartTime,
                    EndTime: LeftEndTime,
                });
            },

            close() {
                this.mailModel = false;
                // $close(this);
            },

            handleSubmit(_that) {
                let json = {...this.model };
                this.$post("weima/jiepu_email/save", { "model": json }, function(res) {
                    let data = res.data;
                    _this.$Message.success(data.Message);
                    _this.id = data.Data;
                    // if (_that) {
                    //     _that.editData.flag = false
                    //     _that.init()
                    // } else {
                    //     _this.$router.replace({
                    //         path: "edit",
                    //         query: {
                    //             id: data.Data,
                    //         }
                    //     })
                    // }
                    this.mailModel = false;
                    // if (data.Data1) {
                    //     _this.model = data.Data1;
                    // }
                })
            },
            canclessss() {
                this.popVisible = false;
            },
            conformsss() {
                this.popVisible = false;
                $setCookie("RefreashTimeNumber", this.timeNumber);
                window.location.reload()
                    /*
                     setTimeout(function() {
                             window.location.reload()
                         }, this.timeNumber * 1000)
                         */
                    // setTimeout(window.location.reload(), this.timeNumber * 1000);
            },


            ok() {
                // 用户名密码
                // $getCookie('projectId');
                // $getCookie('guid');
                let that = this;
                this.$post("weima/jiepu/login", {
                    username: this.emailAccount.username,
                    password: this.emailAccount.password,
                }, function(res) {
                    that.$post("weima/jiepu_email/get", {}, function(res) {
                        if (res && res.data && res.data) {
                            let model = res.data;
                            that.model = Object.assign({}, model, {
                                MonthSend: parseInt(model.MonthSend),
                                WeekSend: parseInt(model.WeekSend),
                            })
                            that.mailModel = true;
                        }
                    });
                });
            },
            refreshPage(e) {
                setTimeout(window.location.reload(), e);
            },
            // 邮件设置
            mailSet(row) {
                this.modal1 = true;
                // this.$router.push({
                //     path: "/weima/jiepu_email/edit",
                //     query: {
                //         id: row ? row.ID : 1,
                //     }
                // })

            },
            getBaozhuangData(params) {
                var that = this;
                this.$post('weima/jiepu/getBaozhuangData', params, function(res) {
                    if (res && res.data && res.data.Data) {
                        that.BZData = res.data.Data;

                    }

                })
            },

            // 中间列表展示
            showMiddleList(params) {
                let that = this;
                this.getBaozhuangData(params);

                this.$post('weima/jiepu/getlist', params, function(res) {
                    if (res && res.data && res.data.Data && res.data.Data.Items) {
                        var items = res.data.Data.Items;
                        that.items = items.map(function(item) {
                            return Object.assign({}, item, {
                                OEE: item.OEE, //  * 100
                                DownTime: item.DownTime,
                                FPY: item.FPY, //  * 100
                                Scrap: item.Scrap, //  * 100
                            });
                        });
                        // that.items = res.data.Data.Items.slice(0, that.search1.onePageCount);

                    } else {
                        return [];
                    }

                    that.spinShow = false;
                    that.allData = res.data.Data.Items; // 所有数据
                    that.PageTotal = res.data.Data.DataTotal;
                    if (that.items[0]) {
                        that.detail = that.items[0];
                    }
                    if (that.items && that.items[0]) {
                        var datas = [
                            { value: that.items[0].OEE, name: 'OEE' },
                            { value: that.items[0].DownTime, name: 'DownTime' },
                            { value: that.items[0].FPY, name: 'FPY' },
                            { value: that.items[0].Scrap, name: 'Scrap' },
                        ];
                        that.chart3(datas);
                    }

                })

            },
            // 中间状态变化
            statusChange1(e) {
                this.search1.State = e;
                let params = { State: e, StartTime: this.search1.StartTime, EndTime: this.search1.EndTime, Devices: this.search1.Devices };
                this.showMiddleList(params);
            },

            // 中间时间变化
            timeChange1(e) {
                this.search1.StartTime = e[0];
                this.search1.EndTime = e[1];
                $setCookie('CenterStartTime', e[0])
                $setCookie('CenterEndTime', e[1])
                let params = { State: this.search1.State, StartTime: e[0], EndTime: e[1], Devices: this.search1.Devices };
                this.showMiddleList(params);
            },
            // 中间设备变化设备变化
            deviceChange1(e) {
                this.search.Devices = e;
                let params = { State: this.search1.State, StartTime: this.search1.StartTime, EndTime: this.search1.EndTime, Devices: e };
                this.showMiddleList(params);
            },
            onGroupRowClick(r, row) {

                let that = this;
                this.detail = r;
                let datas = [
                    { value: that.items[0].OEE, name: 'OEE' },
                    { value: r.DownTime, name: 'DownTime' },
                    { value: r.FPY, name: 'FPY' },
                    { value: r.Scrap, name: 'Scrap' }
                ];
                that.chart3(datas);
            },
            newpagechange: function(val) {
                this.items = this.allData.slice(this.search1.onePageCount * (val - 1), this.search1.onePageCount * val);
            },


            pageSizechange: function(val) {
                this.search1.onePageCount = val;

                // 每页条数
                if (this.allData.length > 0) {
                    this.items = this.allData.slice(0, val);
                }
            },
            selectItem: function(val) {
                this.selectedKeys = val;
            },
            // 左侧图标展示
            showLeftChart(params) {
                let that = this;
                this.$post('weima/jiepu/getLeftData', params, function(res) {

                    var Data = res.data.Data
                    that.leftChart = {
                        DownTime: Data.DownTime,
                        OEE: Data.OEE, //  * 100
                        FPY: Data.FPY, //  * 100
                        Scrap: Data.Scrap, //  * 100
                    };
                    let datass = [
                        { value: that.leftChart.OEE, name: 'OEE schedule-%' },
                        { value: 100 - that.leftChart.OEE, name: 'others' },
                    ];
                    /*let data2 = [
                        res.data.Data.DownTime,
                        res.data.Data.Pause,
                        res.data.Data.Running,
                        res.data.Data.OEE
                    ];*/
                    let data2 = [
                        that.leftChart.OEE,
                        that.leftChart.DownTime,
                        that.leftChart.FPY,
                        that.leftChart.Scrap,
                    ];

                    that.chart1(datass);
                    that.chart2(data2);
                })

            },

            // 左侧机器变化设备变化
            deviceChange(e) {
                this.search.Devices = e;
                let params = {
                    State: this.search.State,
                    StartTime: this.search.StartTime,
                    EndTime: this.search.EndTime,
                    Devices: e,
                    isAllData: true,
                };
                this.showLeftChart(params);
            },

            // 左侧时间变化
            timeChange(e) {
                this.search.StartTime = e[0];
                this.search.EndTime = e[1];
                let params = {
                    State: this.search.State,
                    StartTime: e[0],
                    EndTime: e[1],
                    Devices: this.search.Devices,
                    isAllData: true,
                };
                $setCookie('LeftStartTime', e[0])
                $setCookie('LeftEndTime', e[1])
                this.showLeftChart(params);
            },





            // 系统时间格式化 （中英环境，防止中文出现）
            getNowFormatDate() {
                var date = new Date();
                var month = date.getMonth() + 1;
                var strDate = date.getDate();
                if (month >= 1 && month <= 9) {
                    month = "0" + month;
                }
                if (strDate >= 0 && strDate <= 9) {
                    strDate = "0" + strDate;
                }
                var currentDate = date.getFullYear() + "-" + month + "-" + strDate +
                    " " + date.getHours().toString().padStart(2, "0") + ":" + date.getMinutes().toString().padStart(2, "0") + ":" + date.getSeconds().toString().padStart(2, "0");
                return currentDate;
            },

            // 获得机器数据
            getDevices() {
                let params = { isAllData: true };
                let that = this;
                this.$post('weima/slaveDevice/getlist', params, function(res) {
                    that.deviceList = res.data.Data.Items;
                })
            },

            // 获得状态数据
            getStatus() {
                let params = { isAllData: true };
                let that = this;
                this.$post('weima/slaveState/getlist', params, function(res) {
                    that.statuList = res.data.Data.Items;

                })
            },

            // 语言切换
            langugeChange(e) {
                this.model11 = e;
                $setCookie("languge", e);
            },
            //  左侧饼图
            chart1(data) {
                var myChart1 = echarts.init(document.getElementById('main1'));
                var options = {
                    title: {
                        text: data[0].value + '%',
                        textStyle: {
                            color: '#ffffff',
                            fontSize: 30
                        },
                        itemGap: 20,
                        left: 'center',
                        top: '43%'
                    },
                    /*tooltip: {
                        trigger: 'item',
                        formatter: '{a} <br/>{b}: {c} ({d}%)'
                    },
                    */
                    color: ['#f00', '#eeeeee'], //  
                    legend: {
                        orient: 'vertical',
                        left: 10,
                        data: ['OEE schedule-%'],
                        textStyle: {
                            color: 'white',
                            fontSize: 12
                        }
                    },
                    series: [{
                        name: '',
                        type: 'pie',
                        radius: ['50%', '70%'],
                        avoidLabelOverlap: false,
                        label: {
                            show: false,
                            position: 'center'
                        },
                        emphasis: {
                            label: {
                                textStyle: {
                                    fontSize: 10,
                                    color: 'white'
                                }

                                // show: true,
                                // fontSize: '30',
                                // fontWeight: 'bold',
                                // color: 'white'
                            }
                        },
                        labelLine: {
                            show: false,
                            // normal: {
                            //     show: true,
                            //     position: 'outer',
                            //     formatter:'{b}\n{d}%',
                            //     color:'#000'
                            // }
                        },
                        data: data,
                        hoverAnimation: false
                    }]
                };
                myChart1.setOption(options);
            },

            // 左侧条形图
            chart2(data) {
                var myChart = echarts.init(document.getElementById('main'));
                option = {
                    title: {
                        text: '',
                        subtext: ''
                    },
                    // color: ['#6869f7'],
                    tooltip: {
                        trigger: 'axis',
                        formatter: function(params, ticket, callback) {
                            var item = params[0]
                            if (item.dataIndex == 1) {
                                return params[0].name + ': ' + params[0].value + 'h';
                            }
                            return params[0].name + ': ' + params[0].value + '%';
                        },
                        axisPointer: {
                            type: 'shadow'
                        }
                    },
                    legend: {
                        data: ['OEE', 'DownTime', 'FPY', 'Scrap'],
                    },
                    grid: {
                        left: '3%',
                        right: '4%',
                        bottom: '3%',
                        containLabel: true
                    },
                    xAxis: {
                        type: 'value',
                        boundaryGap: [0, 0.01],
                        axisLabel: {
                            show: true,
                            textStyle: {
                                color: '#fff',
                                fontSize: '12'
                            }
                        },
                    },
                    yAxis: {
                        type: 'category',
                        data: ['OEE', 'DownTime', 'FPY', 'Scrap'],
                        axisLabel: {
                            show: true,
                            textStyle: {
                                color: '#fff'
                            }
                        },
                    },
                    series: [{
                        legend: {
                            show: true
                        },
                        type: 'bar',
                        data: data,
                        backgroundStyle: {
                            color: 'rgba(220, 220, 220, 0.8)'
                        },
                        itemStyle: {
                            normal: {
                                color: function(params) {
                                    var colorList = ['#ff6a66', '#c064f8', '#5ebe67', '#3c90f7'];
                                    return colorList[params.dataIndex]
                                }
                            }
                        },
                        label: {
                            show: true,
                            formatter: function(params, ticket, callback) {

                                var item = params
                                if (item.dataIndex == 1) {
                                    return params.value + 'h';
                                }
                                return params.value + '%';
                            },
                            fontSize: 14,
                            rich: {
                                name: {
                                    textBorderColor: '#fff'
                                }
                            }
                        }
                    }]
                };
                myChart.setOption(option);
            },

            // 右侧饼图
            chart3(data) {

                var chart = echarts.init(document.getElementById('optionPies'));
                var name = data.map(function(d) {
                    return d.name;
                })

                var value = data.map(function(d) {
                        return d.value;
                    })
                    // var chartPie = echarts.init(document.getElementById('optionPies'));
                optionPies = {
                    title: {
                        left: 'center'
                    },

                    tooltip: {
                        trigger: 'axis',
                        formatter: function(params, ticket, callback) {
                            var item = params[0]
                            if (item.dataIndex == 1) {
                                return params[0].name + ': ' + params[0].value + 'h';
                            }
                            return params[0].name + ': ' + params[0].value + '%';
                        },
                        axisPointer: {
                            type: 'shadow'
                        }
                    },
                    color: ['#ff6a66', '#c064f8', '#5ebe67', '#3c90f7'],
                    xAxis: {
                        type: 'category',
                        data: name,
                        axisLabel: {
                            show: true,
                            textStyle: {
                                color: '#fff'
                            }
                        },
                    },
                    yAxis: {
                        type: 'value',
                        axisLabel: {
                            show: true,
                            textStyle: {
                                color: '#fff'
                            }
                        },
                    },
                    series: [{
                        data: value,
                        type: 'bar',
                        // showBackground: true,
                        backgroundStyle: {
                            color: 'rgba(220, 220, 220, 0.8)'
                        },
                        itemStyle: {
                            normal: {
                                color: function(params) {
                                    var colorList = ['#ff6a66', '#c064f8', '#5ebe67', '#3c90f7'];
                                    return colorList[params.dataIndex]
                                }
                            }
                        },
                        label: {
                            show: true,
                            formatter: function(params, ticket, callback) {

                                var item = params
                                if (item.dataIndex == 1) {
                                    return params.value + 'h';
                                }
                                return params.value + '%';
                            },
                            fontSize: 14,
                            rich: {
                                name: {
                                    textBorderColor: '#fff'
                                }
                            }
                        }
                    }],
                };
                chart.setOption(optionPies);
                // chartPie.setOption(optionPies);

            },

            init() {
                var that = this;
                $setCookie("languge", 1);
                setTimeout(function() {
                    that.init();
                    // window.location.reload();
                }, that.timeNumber * 1000);

                this.initDateTime()

                this.getDevices();
                this.showMiddleList(this.search1);
                this.showLeftChart(this.search);

                setInterval(function() {
                    that.currentTime = that.getNowFormatDate();
                }, 500);

                this.getStatus();
                // _this.search = { ...searchJson, ..._this.$route.query };
            },
            edit: function(row) {
                this.$router.push({
                    path: "/sixiang/matchFoodAuto/edit",
                    query: {
                        id: row ? row.ID : 0,
                    }
                })
            },
            asyncOK: function() {
                this.editData.flag = true
                this.$refs.edit.handleSubmit(this)
            },
            selectItem: function(val) {
                this.selectedKeys = val;
            },





            getIndex: function() {
                this.index += 1;
            },
            // getTree: function(){
            //     _this = this
            //     this.$post('public/web_category/getTree', {id: this.id,isLeftMenu:true, userId: $getCookie("CurrentUserID"), isLoadPermissions: true}, function(res) {
            //        _this.nodes = res.data.Data
            // 	   _this.spinShow = false
            //     },function(){},{ts:false})
            // },
            handleClick(name) {
                switch (name) {
                    case 'logout':
                        $delCookie("CurrentUserID")
                        $delCookie("token")
                        let pid = $getCookie("projectId");
                        $delCookie("projectId")
                            //$delCookie("routes")
                        location.href = `login.html#/login?pid=${pid}`
                        break
                    case 'message':
                        this.message()
                        break
                }
            }

        }
    }




    return paths
});