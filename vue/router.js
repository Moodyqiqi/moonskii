define(function(require) {
    var routes = []
    var router = new VueRouter({
        routes // (缩写) 相当于 routes: routes
    })

    window.$project = '' //项目唯一编码
        /**
         * 重写路由的replace方法
         */
    var routerPush = VueRouter.prototype.replace
    var isOneLoading = true
    VueRouter.prototype.replace = function replace(location) {
        return routerPush.call(this, location).catch(error => error)
    }
    if (location.href.indexOf("login.html") == -1 && !$getCookie("token")) {
        //不是登录页，并且未登录 跳转到登录页
        location.href = "login.html#/login"
    } else if (location.href.indexOf("login.html") > -1 && $getCookie("token")) {
        //是登录页并且已经登录，跳转到index
        location.href = "index.html"
    } else if (location.href.indexOf("index.html") > -1 && $getCookie("token")) {
        //是首页，并且登录 ，加载路由
        var http = require("http/index")

        http.post("public/user_account/getRoutes", {
            userId: $getCookie("CurrentUserID"),
            // projectId:$getCookie("projectId"),
        }, function(res) {

            var routersData = res.data.Data
            var loadList = []
            routersData.forEach(item => {
                loadList.push("project/" + item.route)
            })
            require(loadList, function() {
                var routersa = []
                for (var i in arguments) {
                    var index = i
                    var item = arguments[i]

                    if (item != undefined) {
                        //全屏的路由
                        if (true) {
                            routersa.push({
                                path: '/echart',
                                component: function(resolve) {
                                    return require(['project/weima/echart/echart'], resolve)
                                }
                            })
                        }
                        routersa.push({
                            path: '/' + routersData[index].name,
                            component: function(resolve) {
                                return require(['view/index/index'], resolve)
                            },
                            // 	component: function(resolve) {
                            // 	return require(['view/index/index1'], resolve)
                            // },

                            component: function(resolve) {
                                return require(['view/index/index1'], resolve)
                            },

                            component: function(resolve) {
                                return require(['view/index/index'], resolve)
                            },

                            children: [{
                                    path: '',
                                    redirect: '/list'
                                },
                                {
                                    path: '/404',
                                    component: function(resolve) {
                                        return require(['view/404/404'], resolve)
                                    }
                                },
                                ...item
                            ]
                        })
                    }

                }
                router.addRoutes(routersa)
                    //首次进页面
                isOneLoading = false
            })

        }, function(err) {}, { ts: false })

        //var routersData = JSON.parse($getCookie("routes"))
        //console.log(routersData)

    } else {
        //加载登录路由
        //count++
        router.addRoutes([{
            path: '/login',
            component: function(resolve) {
                return require(['view/login/login'], resolve)
            }
        }, {
            path: '/echarts',
            component: function(resolve) {
                return require(['../project/echart/echart'], resolve)
            }
        }])
    }


    router.beforeEach(function(to, from, next) {

        iview.LoadingBar.start()
        if (isOneLoading) {
            //刷新页面第一次加载导致空白，单独处理
            next()
            return
        }

        if (to.path == "/login" || to.path == "/404" || to.path == "/echarts") {
            next()
            return
        }
        //未登录
        if (!$getCookie("token")) {
            next("/login")
            return
        }

        $post("public/web_permissions/getPermissions", {
            url: to.path
        }, function(res) {

            if (to.path.indexOf("edit") > -1 && res.data.Data.edit || res.data.Data.list) {
                next()

            } else {
                next("/404")
                iview.LoadingBar.finish()
            }

        }, function(err) {
            next("/404")
            iview.LoadingBar.finish()
        }, {
            ts: false
        })

    })

    router.afterEach(route => {
        iview.LoadingBar.finish()
    })



    return router
});