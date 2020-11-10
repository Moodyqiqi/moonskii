define(function(require) {
    var tep = require("text!./list.html");
    var css1 = require("text!./list.css");
    var css2 = require("text!../../../../../css/list.css");
    var css = css1 + css2;
    addCss(css, 'settlement_rules-list');
    var _this = null;
    var searchJson = {
        Keyword: '',
        PageIndex: 1,
        onePageCount: 30,
        startID: 0,
        endID: 0,
        startBatchID: 0,
        endBatchID: 0,
        startItemID: 0,
        endItemID: 0,
        EmployeeID: '|',
        SettlementType: '|',
        RulType: '|',
        startAttendanceDays: 0,
        endAttendanceDays: 0,
        startPunchInDays: 0,
        endPunchInDays: 0,
        startWorkingDays: 0,
        endWorkingDays: 0,
        startDateWorking: 0,
        endDateWorking: 0,
        startCreateDate: '',
        endCreateDate: '',
        startCreateUserID: 0,
        endCreateUserID: 0,
        startUpdateDate: '',
        endUpdateDate: '',
        startUpdateUserID: 0,
        endUpdateUserID: 0,
        startCompanyID: 0,
        endCompanyID: 0,
    };
    var paths = {
        template: tep,
        data: function() {
            return {
                importMessage: null,
                processData: 0,
                model: {
                    texts: '',
                },
                BASEURL: globalUrl,
                importExcel: false,
                isSecondStep: false,
                step: 0,
                PageTotal: 0,
                columns: [{
                        type: 'selection',
                        align: 'center',
                    },
                    {
                        title: '工号',
                        type: 'index',
                        align: 'center',
                    },
                    {
                        title: '姓名',
                        key: 'ID',
                        align: 'center',
                    },
                    {
                        title: '厂别',
                        align: 'center',
                        key: 'EmployeeID_ID',
                    },
                    {
                        title: '供应商',
                        align: 'center',
                        key: 'SettlementRuleType_Title',
                    },
                    {
                        title: '状态',
                        key: 'RuleName',
                        align: 'center',
                    },
                    {
                        title: '组别',
                        key: 'AttendanceDays',
                        align: 'center',
                    },
                    // {
                    //     title: '更新时间',
                    //     key: 'DateOfEmployment',
                    //     align: 'center',
                    // },
                ],
                search: {},
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
                staffs: '',

                Type1_Items: [],
                Type1_Title: '',
                Type2_Items: [],
                Type2_Title: '',
                editItemVisible: false,
                editItemId: 0,
                staffsSome: '',
                staffsShow: '',
                staffsNameShowAll: 0,
            }
        },
        computed: {

            staffsNameString: function() {
                if (!this.staffs || String(this.staffs).length <= 240) {
                    return this.staffs;
                }
                if (this.staffsNameShowAll) {
                    return this.staffs;
                } else {
                    return String(this.staffs).substr(0, 240) + '......';
                }
            },
        },
        mounted: function() {
            _this = this;
            this.$nextTick(function() {
                this.one();
            });
            /*
            this.init();
            _this.getPermissions();
            _this.getStaffs();
            */

        },
        watch: {
            '$route': {
                handler: function(n, o) {
                    this.init();
                },
                deep: true
            }
        },
        components: {
            'edit-template': require("../edit/edit"),
            'edit-wrap': require("./components/edit"),
        },
        methods: {

            one: function() {
                this.init();
                this.getPermissions();
                this.getStaffs();
            },
            remove: function(row) {
                this.$Modal.confirm({
                    title: '删除警告',
                    content: '<p>您确定删除该项数据吗？</p>',
                    loading: true,
                    closable: true,
                    onOk: () => {
                        let _this = this;
                        this.$post('sixiang/settlement_rules/delete', { id: row.ID }, function(res) {
                            let data = res.data;
                            if (data.Succeed) {
                                _this.getList(_this.search);
                                _this.$Modal.remove();
                            }
                        })
                    }
                });
            },
            confirm() {
                this.$Modal.confirm({
                    title: '发布提示',
                    content: '<p>您确定给选中员工发布此规则吗？</p>',
                    onOk: () => {
                        this.$post("sixiang/settlement_rules/releaseRules", {
                            id: _this.$route.query.id,
                        }, function(res) {
                            let data = res.data;
                            if (data.Succeed) {
                                this.$router.push({
                                    path: "/sixiang/employee_data",
                                    query: {
                                        IsSetPolicy: 0,
                                        Factory: _this.$route.query.Factory,
                                    }
                                })
                            }
                        })

                    },
                    onCancel: () => {

                    }
                });
            },
            // 关闭窗口
            closePop() {
                this.importExcel = false;
                this.importMessage = null;
                this.processData = 0;
            },
            handleSubmit() {
                let method = 'sixiang/sixiang_employee_data/importExcel';
                let json = {...this.model };
                this.$post(method, json, function(res) {
                    _this.model.texts = res.data.Data;
                    if (res.data.Succeed) {
                        _this.processData = 100;
                    }
                    _this.importMessage = res.data.Message;
                })
            },
            uploadFiles: function(files) {
                this.model.Files = files
            },
            // 导入员工
            importEmployee() {
                this.importExcel = true;
            },
            // 跳到第一步
            toStep1() {
                this.step = 0;
                this.isSecondStep = false;
            },
            // 跳到下一步
            toStep2() {
                this.step = 1;
                this.isSecondStep = true;
            },
            init() {
                console.log(_this)
                _this.search = {...searchJson, ..._this.$route.query };
                this.getList(_this.search, true);
            },
            getPermissions: function() {
                this.$post("public/web_permissions/getPermissions", {
                    url: this.$route.path,
                    userId: $getCookie("CurrentUserID"),
                }, function(res) {
                    _this.permissions = res.data.Data.items;
                })
            },
            listSearch: function() {
                _this.search.PageIndex = 1;
                _this.search.timestamp = new Date().getTime();
                _this.$router.replace({
                    path: _this.$route.path,
                    query: _this.search,
                })
            },
            clearSearch: function() {
                _this.$router.replace({
                        path: _this.$route.path,
                        query: {
                            ...searchJson,
                        }
                    })
                    //_this.$router.go(0);
            },

            // /sixiang/sixiang_employee_data/getList
            getList: function(json, isCloseRight = false) {
                let params = {...json };
                params.isAllData = true;
                delete params.timestamp;
                this.spinShow = true;
                this.$post('sixiang/settlement_rules/getlist', params, function(res) {
                    _this.items = res.data.Data.Items;
                    var items = res.data.Data.Items;
                    _this.Type1_Items = items.filter(function(d) {
                        if (d.SettlementType == 1) {
                            _this.Type1_Title = d.SettlementType_Title;
                            return d;
                        }
                    });
                    _this.Type2_Items = items.filter(function(d) {
                        if (d.SettlementType == 2) {
                            _this.Type2_Title = d.SettlementType_Title;
                            return d;
                        }
                    });
                    console.log('_this.Type1_Items', _this.Type1_Items)
                    console.log('items', res.data.Data.Items)

                    _this.search.PageIndex = res.data.Data.PageIndex;
                    _this.spinShow = false;
                    _this.PageTotal = res.data.Data.DataTotal;
                    if (isCloseRight) {
                        _this.drawerFlag = false;
                    }
                })
            },
            handleEditClose: function() {
                this.editItemVisible = false;
                this.editItemId = 0;
                this.one();
            },
            edit: function(row) {
                console.log('row', row);
                this.editItemId = row ? row.ID : 0;
                this.editItemBid = _this.$route.query.id;
                this.editItemVisible = true;
                console.log('editItemId', this.editItemId);
                /*
                this.$router.push({
                    path: "/sixiang/settlement_rules/edit",
                    query: {
                        id: row ? row.ID : 0,
                        bid: _this.$route.query.id,
                    }
                });
                */

                // if (_this.isOpenWindow){
                //     this.editData.flag = true
                //     this.editData.id = row ? row.ID : 0
                // }
                // else{
                // this.$router.push({
                //     path: "/sixiang/settlement_rules/edit",
                //     query: {
                //         id: row ? row.ID : 0,
                //     }
                // })
                // }
            },
            asyncOK: function() {
                this.editData.flag = true
                this.$refs.edit.handleSubmit(this)
            },
            remove: function(row) {
                this.$Modal.confirm({
                    title: '删除警告',
                    content: '<p>您确定删除该项数据吗？</p>',
                    loading: true,
                    closable: true,
                    onOk: () => {
                        let _this = this;
                        this.$post('sixiang/settlement_rules/delete', { id: row.ID }, function(res) {
                            let data = res.data;
                            if (data.Succeed) {
                                _this.getList(_this.search);
                                _this.$Modal.remove();
                            }
                        })
                    }
                });
            },
            removes: function() {
                this.$Modal.confirm({
                    title: '删除警告',
                    content: `<p>您确定删除这${this.selectedKeys.length}条数据吗？</p>`,
                    loading: true,
                    closable: true,
                    onOk: () => {
                        let _this = this;
                        this.$post('sixiang/settlement_rules/delete', {
                            ids: _this.selectedKeys,
                        }, function(res) {
                            let data = res.data;
                            if (data.Succeed) {
                                _this.getList(_this.search);
                                _this.$Modal.remove();
                            }
                        })
                    }
                });
            },
            selectItem: function(val) {
                this.selectedKeys = val;
            },
            exportExcel: function() {
                let params = {...this.search };
                params.EmployeeID = params.EmployeeID ? params.EmployeeID.split('|')[0] : '';
                params.SettlementRuleType = params.SettlementRuleType ? params.SettlementRuleType.split('|')[0] : '';

                this.$post('sixiang/settlement_rules/exportExcel', params, function(res) {
                    let data = res.data;
                    if (data.Succeed) {
                        var elemIF = document.createElement("iframe");
                        elemIF.src = `${globalUrl}${data.Data}`;
                        elemIF.style.display = "none";
                        document.body.appendChild(elemIF);
                        setTimeout(() => {
                            elemIF.remove();
                        }, 5000);
                    }
                })
            },
            getStaffs: function() {
                var that = this;
                this.$post("sixiang/settlement_rules_batch/getEmployeeNames", {
                    id: this.$route.query.id,
                }, function(res) {
                    let data = res.data;
                    _this.staffs = data.Data;
                    var staffs = data.Data;

                    that.staffsSome = String(staffs).substr(0, 240) + '......';
                    // that.staffsAll = staffs
                    /*
                    if (staffs && String(staffs).length > 240) {
                        that.staffsSome = String(staffs).substr(0, 240) + '......';
                        that.staffsAll = staffs
                    } else {
                        that.staffsSome = String(staffs).substr(0, 240) + '......';
                        that.staffsAll = staffs
                    }
                    */

                })
            },
        }
    }
    return paths
});