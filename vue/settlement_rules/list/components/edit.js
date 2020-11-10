define(function(require) {
    var tep = require("text!./edit.html");
    var css1 = require("text!./edit.css");
    // var css2 = require("text!../../../../../css/edit.css");
    // var css = css1 + css2;
    var css = css1;
    addCss(css, 'settlement_rules-edit');
    var _this = null
    var paths = {
        template: tep,
        props: {
            id: {
                type: Number,
                default: 0,
            },
            bid: {
                type: Number,
                default: 0,
            },
            visible: {
                type: [Boolean, Number, String],
                default: 0,
            }
        },
        data: function() {
            return {
                model: {},
                // id: this.$route.query.id,
                BASEURL: globalUrl,
                permissions: {},
                ruleItems: []
            }
        },
        mounted: function() {
            _this = this;
            this.$nextTick(function() {
                this.one();
            });
        },
        methods: {
            one: function() {
                this.getData();
                this.getPermissions();
            },
            add() {
                let i = 0
                this.ruleItems = this.ruleItems ? this.ruleItems : [];
                this.ruleItems.map((item, index) => {
                    if (item.index > i) {
                        i = item.index
                    }
                })
                this.ruleItems.push({ index: i + 1 })

            },
            removeMock(row) {
                list = new Array()
                this.ruleItems.map((item, index) => {
                    if (row.index != item.index) {
                        list.push(item)
                    }
                })
                this.ruleItems = list
            },
            close() {
                this.$emit('close', true);
            },
            getData: function() {
                this.$post("sixiang/settlement_rules/get", {
                    ID: this.id,
                }, function(res) {
                    _this.model = res.data;
                    _this.ruleItems = _this.model.ruleItems;
                })
            },

            getPermissions: function() {
                this.$post("public/web_permissions/getPermissions", {
                    url: this.$route.path,
                    userId: $getCookie("CurrentUserID"),
                }, function(res) {
                    _this.permissions = res.data.Data.items;
                })
            },
            handleSubmit(_that) {
                var that = this;
                this.ruleItems = this.ruleItems || []
                for (let i of this.ruleItems) {
                    i.Date = $formatDate(i.Date, 'yyyy-MM-dd hh:mm');
                }
                this.model.ruleItems = this.ruleItems
                let json = {...this.model };
                console.log(json, this.model);
                json.BatchID = json.BatchID ? json.BatchID : this.bid;
                json.CreateDate = $formatDate(json.CreateDate, 'yyyy-MM-dd hh:mm:ss');
                json.UpdateDate = $formatDate(json.UpdateDate, 'yyyy-MM-dd hh:mm:ss');
                this.$post("sixiang/settlement_rules/save", { "model": json }, function(res) {
                    let data = res.data;
                    _this.$Message.success(data.Message);
                    _this.id = data.Data;
                    that.$emit('close', true)
                    return;

                    console.log('post', res)
                        /*if (_that) {
                            _that.editData.flag = false
                            _that.init()
                        } else {
                            _this.$router.replace({
                                path: "edit",
                                query: {
                                    id: data.Data,
                                }
                            })
                        }
                        */
                    if (data.Data1) {
                        _this.model = data.Data1;
                    }
                })
            },
        },
        watch: {
            '$route': function(n, o) {
                this.getData()
            },
            id: function(n, o) {
                this.one();
            },
            bid: function(n, o) {
                this.one();
            },
            visible: function(n, o) {
                this.one();
            },

        },
    }
    return paths
});