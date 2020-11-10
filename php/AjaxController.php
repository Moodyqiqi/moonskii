<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Precut;
use App\Models\PrecutToVehicleCoverage;
use App\Models\UserSync;
use App\Models\Activity;
use App\Models\Cities;
use App\Models\FilmTypeMaster;
use App\Models\MakeMaster;
use App\Models\PartMaster;
use App\Models\ProvinceMaster;
use App\Models\RegionMaster;
use App\Models\RollMaster;
use App\Models\RollRestlen;
use App\Models\Warranty;
use App\Models\WarrantyVerify;
use Foo\Bar\Baz;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;

class AjaxController extends Controller
{
    protected  $language = 0;
    protected  $language_column = '';

    /*
     * get方法仅限get访问，post仅限post访问
     * delete与put即可以delete或put访问，也可以post访问
     * articleList
     * articlesDelete ID 为数组形式，id=>[]
     *
     * */
    protected $get = [
        'lang_change',
        'regions_json_data',
        'make_json_data',
        'coverages_json_data',
        'phpinfo',
        'getUsersByType',
        'searchRollsByRollNumber',
        'getResidueLengthByRollNumber',
        'getResidueLengthForCoverageByRollNumber',
        'searchAllRollsByRollNumber',
        'getGMSByRollNumber',
        'getResidueLengthByRollNumberAndGmsId',
        'handleWFQSH',
        'findGMSByRN',
        'test1',
        'getRestlen',
        'getUsers',
        'getURRL',  // 模糊查询卷号
        'getRGMS',
        'getLog',
        'user_sync_test',
        'getSdmc',
        'getURns',
        'getPVC',
        'getRRl',
        'getRRn',
        'getWSF',
        'performance',
        'pChart',
        'getList',
        'tongji',
        'getJXSMC',
    ];
    protected $delete = [
    ];
    protected $put = [
    ];
    protected $post = [
        'checkDistributorUniqueId',
        'upload_file',
        'getUsersByType',
        'handleWFQSH',
        'getDealers',
        'user_sync_h',
        'sendSMS',
        'editWarrantyCustomer',
        'roles',
        'warranty',
        'user_sync'
    ];
    /*
     * 用户同步*/
    public function user_sync(Request $request)
    {
        $admin_id = session('admin.id');
        $t = $request->input('t');
        $id = $request->input('id');
        checkAuthority();

        if ($t == 'getUid1') {
            $user_id = (int) $request->input('user_id');
            $user_type = (int) $request->input('user_type');
            if (!in_array($user_type, [2,3])) {
                return fs403('仅支持省代与经销商同步');
            }
            $select = DB::table('users')
                ->where('user_type', $user_type)
                ->whereNull('deleted_at')
                ->where(function ($query) use ($user_id) {
                    $query->where('id', $user_id)
                        ->orWhereNotIn('id', function($q) {
                            $q->select('user_id')->from('users_union');
                        });
                })
                ->get(['id as value', 'abbr as label']);

            return fs200d('成功！', $select);
        } elseif ($t == 'changeUserId') {
            $user_id = (int) $request->input('user_id');
            $model = UserSync::find($id);
            if (empty($model) || empty($model->id)) {
                return fs404('没有找到数据');
            }
            $model->update_userid($user_id);
            return fs200('修改成功！');
        } elseif ($t == 'delUnionId') {
            $model = UserSync::find($id);
            if (empty($model) || empty($model->id)) {
                return fs404('没有找到数据');
            }
            $model->delete_userid();
            return fs200('删除成功！');
        } elseif ($t == 'update') {
            /*foreach (range(7874, 7906) as $idd) {
                $model = UserSync::find($idd);
                $type = (int) $request->input('type');
                // 1新增2更新3抛弃
                if ($type == 3) {
                    $model->status = 3;
                    $model->save();
                } else {
                    $bool = $model->dispatch();
                    if (@$bool['status'] != 'SUCCESS') {
                        // return fs403(@$bool['msg']);
                    } else {
                        $model->status = 1;
                        $model->save();
                        // return fs200d('成功！', $bool['data']);
                    }
                }
            }*/
            $model = UserSync::find($id);
            $type = (int) $request->input('type');
            // 1新增2更新3抛弃
            if ($type == 3) {
                $model->status = 3;
                $model->save();
            } else {
                $bool = $model->dispatch();
                if (@$bool['status'] != 'SUCCESS') {
                    return fs403(@$bool['msg']);
                } else {
                    $model->status = 1;
                    $model->save();
                    return fs200d('成功！', $bool['data']);
                }
            }
            return fs200('成功！');
        }

        return fs403('禁止访问');

    }

    /*
     * 质保*/
    public function warranty(Request $request)
    {
        $admin_id = session('admin.id');
        $t = $request->input('t');

        if ($t == 'getInfoByPhone') {
            $phone_number = $request->input('phone_number');
            $data = Warranty::where('phone_number', $phone_number)
                ->select(['first_name','email_address','region_id','province_id','city','address','zip','phone_number'])
                ->first();

            return fs200d('获取成功', $data);
        }
        return fs400('未知操作');
    }

    /*
     * 角色操作*/
    public function roles(Request $request)
    {
        $admin_id = session('admin.id');
        checkAuthority();
        $t = $request->input('t');
        $bid = (int) $request->input('bid');
        $id = (int) $request->input('id');
        if ($t == 'getUserInfo') {
            if (empty($id)) {
                return fs400('参数不正确！');
            }
            $model = Admin::find($id);
            if (empty($model->id)) {
                return fs400('没有找到用户！');
            }
            return ['status' => 'SUCCESS', 'data'=> [
                'id' => $model->id,
                'unique_id' => $model->unique_id,
                'first_name' => $model->first_name,

            ]];
        } elseif ($t == 'addOrUpdate') {
            if (empty($bid)) {
                return fs400('参数不正确！');
            }
            if ($bid <= 3) {
                return fs400('不支持修改该角色用户！');
            }
            $unique_id = $request->input('unique_id');
            $unique_id = trim($unique_id);
            $password = $request->input('password');
            $password = trim($password);
            $first_name = $request->input('first_name');
            $first_name = trim($first_name);
            if (empty($first_name)) {
                return fs400('联系人不能为空');
            }
            $model = Admin::find($id);
            if (empty($model->id)) {
                if (empty($unique_id) || strlen($unique_id) < 6) {
                    return fs400('账号不能小于6位');
                }
                if (empty($password) || strlen($password) < 6) {
                    return fs400('密码不能小于6位');
                }
                $count = Admin::withTrashed()->where('unique_id', $unique_id)
                    ->count('id');
                if ($count >= 1) {
                    return fs400('账号重复，请换一个重试');
                }
                $model = new Admin();
                $model->unique_id = $unique_id;
                $model->password = $password;
                $msg = '添加成功！';
            } else {
                $msg = '修改成功！';
            }
            $model->first_name = $first_name;
            $model->abbr = $first_name;
            $model->username = $first_name;
            $model->company_name = $first_name;
            $model->user_type = $bid;
            $model->status = 1;
            $model->created_by = $admin_id;
            $model->save();
            return fs200($msg);
        } elseif ($t == 'editStatus') {
            $model = Admin::find($id);
            if (empty($model->id)) {
                return fs400('没有找到用户！');
            }
            $status = (int) $request->input('status');
            $model->status = (int) (!$model->status);
            $model->save();
            return fs200('更新成功！');
        } else {
            return fs400('未知操作');
        }

    }

    /*
     * 调货时模糊查询经销商名称*/
    public function getJXSMC(Request $request)
    {
        $user_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');

        $sdid = $request->input('sdid');
        $q = $request->input('q');
        $q = trim($q);
        if (empty($sdid) || empty($q)) {
            return ['status'=>'SUCCESS', 'data'=>[]];
        }
        /*$model = DB::table('roll_restlen')->where('roll_number', 'like', '%'.$q.'%')
            ->where('restlen', '>', 0)
            ->groupBy('roll_number');*/
        $model = Admin::where(function ($query) use ($sdid) {
            $query->where('id', $sdid)
                ->orWhere('creator_id', $sdid);
        })
            ->where('abbr', 'like', '%'.$q.'%')
            ->select(['id', 'abbr']);
        /*if ($user_type == 1) {
            $model = $model->where('user_id', '!=', 1);
        } else {
            $model = $model->whereIn('user_id', function ($query) use ($user_id) {
                $query->select('id')->from('users')
                    ->where('creator_id', $user_id);
            });
        }*/
        $datas = $model->limit(10)->get();
        return ['status'=>'SUCCESS', 'data'=>$datas,
            /*'sql'=>$model->toSql(), $user_id, $q*/
        ];

    }

    /*
     * 获取根据省代ID获取每个城市门店数量
     * 可用、停用或禁用、总数*/
    protected function TJd1($id)
    {
        $u = DB::table('users')
            ->where('creator_id', $id)
            ->groupBy('city')
            ->select([DB::raw('count(id) as tX1xYm5Q'), DB::raw('city as oaWonVEI')])
            ->whereNull('deleted_at')
            ->get(['tX1xYm5Q', 'oaWonVEI']);
        $u2 = DB::table('users')
            ->where('creator_id', $id)
            ->where(function ($query) {
                $query->whereNotNull('deleted_at')
                    ->orWhere('status', '!=', 1);
            })
            ->groupBy('city')
            ->select([DB::raw('count(id) as tX1xYm5Q'), DB::raw('city as oaWonVEI')])
            ->get(['tX1xYm5Q', 'oaWonVEI']);
        $d1 = $d2 = $d3 = [];
        foreach ($u as $k => $v) {
            $kk = ts('cities.'.$v->oaWonVEI) ?: '其他';
            $d3[$kk] = $v->tX1xYm5Q;
        }
        foreach ($u2 as $k => $v) {
            $kk = ts('cities.'.$v->oaWonVEI) ?: '其他';
            $d2[$kk] = $v->tX1xYm5Q;
        }
        foreach ($d3 as $k => $v) {
            $d1[$k] = $v - @$d2[$k];
        }
        return [
            'd1' => $d1,
            'd2' => $d2,
            'd3' => $d3,
        ];
    }
    /*
     * 根据省代ID获取每个城市门店数量与级别
     * 仅查询已启用经销商*/
    protected function TJd2($id)
    {
        $u = DB::table('users')
            ->where('creator_id', $id)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->groupBy('city')
            ->groupBy('mdtype')
            ->select([DB::raw('count(id) as tX1xYm5Q'), DB::raw('city as oaWonVEI'), 'mdtype'])
            ->get(['tX1xYm5Q', 'oaWonVEI']);
        $d1 = $d2 = $d3 = [];
        foreach ($u as $k => $v) {
            $kk = ts('cities.'.$v->oaWonVEI) ?: '其他';
            $md = ts('mdtype'.$v->mdtype);
            if (empty($d3[$kk])) {
                $d3[$kk] = [
                    'mdtype0' => 0,
                    'mdtype1' => 0,
                    'mdtype2' => 0,
                    'mdtype3' => 0,
                    'mdtype4' => 0,
                ];
            }
            $mdtype = (int) $v->mdtype;
            $d3[$kk]['mdtype'.$mdtype] = $v->tX1xYm5Q;
            /*if ($md) {
                $d3[$kk]['mdtype'.$v->mdtype] = $v->tX1xYm5Q;
            }*/
        }
        return [
            'data' => $d3,
        ];
    }
    /*
     * 统计*/
    public function tongji(Request $request)
    {
        $a = $request->input('a');
        $id = $request->input('id');
        if ($a == 'md') {
            $data = [];
            $r1 = $this->TJd1($id);
            $r2 = $this->TJd2($id);

            return [
                'status'=>'SUCCESS',
                'data' => [
                    'd1' => $r1['d1'],
                    'd2' => $r1['d2'],
                    'd3' => $r1['d3'],
                    'm1' => $r2['data']
                ],
                $r2
            ];
            return [$u, $u2, $d1, $d2, $d3];
        }

        return ['status'=>'SUCCESS', $a, $id];

    }

    /*
     * 修改已审核通过质保的客户信息
     * 仅管理员可以操作*/
    public function editWarrantyCustomer(Request $request)
    {
        $admin_id = session('admin.id');
        $user_type = session('admin.user_type');
        // checkAuthority();
        $id = $request->input('warranty_id');
        $first_name = $request->input('first_name');
        $phone_number = $request->input('phone_number');
        $extension = $request->input('extension');
        if (empty($first_name) || empty($phone_number) || empty($extension)) {
            return fs2('姓名或联系方式不能为空！');
        }
        $region_id = (int) $request->input('region_id');
        $province_id = (int) $request->input('province_id');
        $city = (int) $request->input('city');
        $address = $request->input('address');
        if (empty($region_id) || empty($province_id) || empty($city) || empty($address)) {
            return fs2('区域/省份/城市/地址不能为空！');
        }
        $zip = $request->input('zip');
        $email_address = $request->input('email_address');
        if (!cEmail($email_address)) {
            return fs2('邮箱格式不符合2！');
        }
        $license_plate = $request->input('license_plate');
        if (empty($license_plate)) {
            return fs2('车牌号不能为空！');
        }
        $model = Warranty::find($id);
        if (empty($model) || empty($model->id)) {
            return fs2('没有找到质保信息！');
        }
        if ($model->approved != 1) {
            return fs2('此处仅支持修改已通过审核的质保信息！');
        }
        if ($user_type == 1) {

        } elseif ($user_type == 2) {
            $d = Admin::withTrashed()->find($model->user_id);
            if (empty($d) || empty($d->id) || ($d->id != $admin_id && $d->creator_id != $admin_id)) {
                return fs2('无操作权限！');
            }
        }
        $alogs = alogs('质保', '修改客户信息', $model->id, null, $model);
        $model->first_name = $first_name;
        $model->phone_number = $phone_number;
        $model->extension = $extension;
        $model->region_id = $region_id;
        $model->province_id = $province_id;
        $model->city = $city;
        $model->address = $address;
        $model->zip = $zip;
        $model->email_address = $email_address;
        $model->license_plate = $license_plate;
        $model->approved = 0;
        $model->fq_user = $admin_id;
        $model->fq_date = date('Y-m-d H:i:s');

        $wv = WarrantyVerify::firstOrNew(['warranty_id'=>$id, 'approved'=>0]);
        $wv->created_by = $admin_id;
        $wv->approved = 0;
        $wv->save();

        $model->save();
        $alogs->new = $model;
        $alogs->save();
        return ['status'=>'SUCCESS', 'msg' => '修改成功！'];
    }
    /*
     * 获取列表
     * a为所选择对象*/
    public function getList(Request $request)
    {
        $a = $request->input('a');
        $limit = (int) $request->input('limit') ?: 15;
        $page = (int) $request->input('page') ?: 1;
        if (empty($a)) {
            return [
                'status' => false,
                'data' => [],
                'total' => 0,
                'msg' => '参数错误'
            ];
        }
        if ($a == 'distributor') {
            $model = Admin::where('user_type', 2);
            $model = filterByColumns($model, ['id', 'abbr', 'company_name','first_name','phone_number', 'first_name']);
            if ($request->input('status')!==null) {
                $status = (int) $request->input('status');
                if ($status != 1) {
                    $status = 0;
                }
                $model = $model->where('status', $status);
            }
        } else {
            return [
                'status' => false,
                'data' => [],
                'total' => 0,
                'msg' => '参数错误'
            ];
        }
        $total = $model->count();
        $data = $model->forPage($page, $limit)->get();
        return [
            'status' => 'SUCCESS',
            'total' => $total,
            'data' => $this->rList($data),
            'msg' => '成功！'
        ];
    }
    protected function rList($data)
    {
        $rows = [];
        $a = request()->input('a');
        if ($a == 'distributor') {
            foreach ($data as $k => $v) {
                $rows[] = [
                    'id' => $v->id,
                    'abbr' => $v->abbr,
                    'company_name' => $v->company_name,
                    'status' => $v->status,
                    'status_txt' => ts('UST'.$v->status),
                    'first_name' => $v->first_name,
                    'phone_number' => rsPN(@$v->phone_number),
                    'ddn' => getDDN($v->id),
                    'ddpn' => getDDPN($v->id),
                ];
            }
        }
        return $rows ?: $data;


    }

    public function sendSMS(Request $request)
    {
        $id = $request->input('id');
        $model = Warranty::findOrFail($id);
        if ($model->approved != 1) {
            return ['status'=>false, 'msg' => '质保尚未通过审核'];
        }
        return sendSMS($model);
        $data = sendSMS();
        $alogs->new = $data;
        $alogs->save();
        return [
            'status'=>'SUCCESS', 'msg' => '当前处于模拟状态，模拟发送成功！'
        ];
    }
    public function pChart(Request $request)
    {
        $id = $request->input('id');
        $type = $request->input('t');
        $admin_id = session()->get('admin.id');
        $admin_type = session()->get('admin.user_type');
        if (empty($id)) {
            return abort(404);
        }
        $user = Admin::withTrashed()->findOrFail($id);
        if (!checkDealerBelongs($id)) {
            return abort(404);
        }
        $model = DB::table('warranty_to_vehicle_coverage as wvc')
            ->leftJoin('warranty as w', 'w.id', 'wvc.warranty_id')
            ->leftJoin('users as u', 'w.user_id', 'u.id')
            ->whereNull('w.deleted_at')
            ->whereIn('w.user_id', function ($query) use ($user) {
                $query->select('id')->from('users')
                    ->where('id', $user->id)
                    ->orWhere('creator_id', $user->id);
            });
        if ($type == 'user') {
            $model = $model->select([DB::raw("u.abbr AS oaWonVEI"), DB::raw('sum(wvc.length) as tX1xYm5Q')])
                ->groupBy("w.user_id");
        } else {
            //  if ($type == 'date')
            $model = $model->select([DB::raw("date_format(w.installation_date,'%m') AS oaWonVEI"), DB::raw('sum(wvc.length) as tX1xYm5Q')])
                ->groupBy("oaWonVEI");
        }
        $data = $model->get();
        $res = [];
        foreach ($data as $k => $v) {
            $res[] = [];
        }
            /*->select([DB::raw("u.abbr AS oaWonVEI"), DB::raw('sum(wvc.length) as tX1xYm5Q')])
            ->groupBy("w.user_id")*/
            // ->select([DB::raw("date_format(w.installation_date,'%m') AS oaWonVEI"), DB::raw('sum(wvc.length) as tX1xYm5Q')])
            // ->groupBy("oaWonVEI")
            // ->get();
        return [$data];
    }

    public function performance(Request $request)
    {
        $id = $request->input('id');
        $user = Admin::withTrashed()->findOrFail($id);
        if ($user->user_type != 2) {
            abort(404);
        }
        $lan = getLangName();
        $model = DB::table('warranty_to_vehicle_coverage as wvc')
            ->leftJoin('warranty as w', 'w.id', 'wvc.warranty_id')
            ->leftJoin('users as u', 'w.user_id', 'u.id')
            ->leftJoin('roll_master as r', 'r.roll_number', 'wvc.roll_number')
            ->leftJoin('film_type_master as fm', 'r.film_type_id', 'fm.id')
            ->whereNull('w.deleted_at')
            // ->groupBy('wvc.id')
            ->whereIn('w.user_id', function ($query) use ($user) {
                $query->select('id')->from('users')
                    ->where('id', $user->id)
                    ->orWhere('creator_id', $user->id);
            });
        $model = filterByDateRange($model, 'w.installation_date');
        $model = filterByColumns($model, ['u.abbr', 'wvc.roll_number', 'w.warranty_id', 'w.phone_number']);
        $sorts = [
            'jxsmc' => 'u.abbr',
            'roll_number' => 'wvc.roll_number',
            'film_type' => 'fm.'.$lan,
            'length' => 'wvc.length',
            'warranty_id' => 'w.warranty_id',
            'date_sold' => 'w.installation_date',
        ];
        $model = sortByColumn($model, $sorts);

        $total = $model->sum('wvc.length');
        $data = $model->select(['wvc.roll_number', 'wvc.length', 'u.abbr as jxsmc', 'w.id as wid', 'w.warranty_id', DB::raw('fm.'.$lan.' as film_type'), 'w.installation_date as date_sold'])
            ->orderByDesc('w.id')->paginate();
        return [
            'rows' => $data->items(),
            'total' => $data->total()
        ];

    }
    /*
     * 根据传入质保ID获取质保信息
     * 用于发送短信*/
    public function getWSF(Request $request)
    {
        $id = $request->input('id');
        $model = Warranty::findOrFail($id);
        if ($model->approved != 1) {
            return ['status'=>false, 'msg' => '质保尚未通过审核'];
        }
        return [
            'status' => 'SUCCESS',
            'data' => [
                'customer_name' => $model->first_name,
                'phone_number' => $model->extension .' ' .$model->phone_number,
                'year' => $model->year_id,
                'make' => ts('make_master.'.$model->make_id),
                'model' => $model->model_id,
            ],
            $model
        ];

    }
    /*
     * 获取用户可退货卷号
     * 仅省代，管理员或经销商返回错误*/
    public function getRRn(Request $request)
    {
        $user_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');
        if ($user_type != 2) {
            return ['status'=>'SUCCESS', 'data'=>[], 'msg'=>'仅可以有省代发起'];
            return ['status'=>false, 'msg'=>'仅可以有省代发起'];
        }
        $roll_number = $request->input('roll_number');
        // $user_id = (int) $request->input('user_id');
        $roll_number = trim($roll_number);
        if (empty($roll_number)) {
            return ['status'=>'SUCCESS', 'data'=>[], 'msg'=>'卷号不能为空'];
        }
        $model = DB::table('roll_sales as rs1')
            ->leftJoin('roll_master as rm', 'rs1.roll_id', 'rm.id')
            ->leftJoin('roll_restlen as rt', 'rm.roll_number', 'rt.roll_number')
            ->where('rs1.sold_by_user_id', 1)
            ->where('rs1.sold_to_user_id', $user_id)
            ->whereNotIn('rm.id', function ($query) use ($user_id) {
                $query->select('roll_id')->from('roll_sales')
                    ->where('sold_by_user_id', $user_id)
                    ->where('type', '<=', 1)
                    ->where('is_precut', '!=', 1);
            })
            ->where('rt.restlen', DB::raw('rm.length'))
            ->where('rt.user_id', $user_id)
            ->whereNull('rm.deleted_at')
            ->where('rm.roll_number', 'like', '%'.$roll_number.'%')
            ->select(['rt.user_id', 'rm.roll_number', DB::raw('sum(rs1.length) as sl'), 'rm.length'])
            ->groupBy('rs1.roll_id');
        $datas = $model->limit(10)->get();
        return ['status'=>'SUCCESS', 'data'=>$datas, /*'sql'=>$model->toSql(), $user_id*/];

    }
    /*
     * 获取当前登录用户剩余长度，发起退货时使用*/
    public function getRRl(Request $request)
    {
        $admin_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');
        if ($user_type == 1) {
            $admin_id = 1;
        }
        $roll_number = $request->input('roll_number');
        $user_id = (int) $request->input('user_id');
        if (empty($roll_number)) {
            return ['status'=>false, 'msg'=>'卷号不能为空'];
        }
        if(empty($user_id)) {
            $user_id = $admin_id;
        } else {
            if (checkDBSs($user_id)){
                return ['status'=>false, 'msg'=>'无操作权限'];
            }
        }
        $roll = RollMaster::where('roll_number', $roll_number)->firstOrFail();
        $model = RollRestlen::where([
            'user_id' => $user_id,
            'roll_number' => $roll_number,
        ])->firstOrFail();
        return [
            'status'=>'SUCCESS',
            'data'=>[
                'restlen'=>@$model->restlen,
                'film_type_id' => $roll->film_type_id,
                'width' => $roll->width,
            ],
        ];
    }

    /*
     * 根据套件ID获取安装部位信息*/
    public function getPVC(Request $request)
    {
        $id = $request->input('id');
        $precut = Precut::findOrFail($id);
        $coverages = PrecutToVehicleCoverage::where('precut_kit_sale_id', $id)
            ->select(['part_id', 'precut_or_manual', 'roll_number', 'film_type_id', 'width', 'length'])
            ->get();
        $precut = DB::table('precut as p')
            ->whereNull('p.deleted_at')
            ->leftJoin('users as x', 'p.user_id', 'x.id')
            ->leftJoin('users as y', 'x.creator_id', 'y.id')
            ->select(['p.user_id', 'x.abbr as jxsmc', 'x.user_type as jxs_type', 'y.abbr as sdmc'])
            ->where('p.id', $id)
            ->first();
        if ($precut->jxs_type != 3) {
            $precut->sdmc = $precut->jxsmc;
        }

        return ['status'=>'SUCCESS', 'data'=>$coverages, 'jxs'=>$precut];

    }
    /*
     * 模糊查询用户卷号，套餐创建时使用*/
    public function getURns(Request $request)
    {
        $user_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');
        $q = $request->input('q');
        $q = trim($q);
        if (empty($q)) {
            return ['status'=>'SUCCESS', 'data'=>[]];
        }
        $id = $request->input('id');
        if (!empty($id)) {
            $p = Precut::find($id);
            if (!empty($p) && !empty($p->user_id)) {
                $user_id = $p->created_by;
            }
        }
        $model = DB::table('roll_master as rm')
            ->leftJoin('roll_restlen as rr', 'rm.roll_number', 'rr.roll_number')
            ->whereNull('rm.deleted_at')
            ->where('rr.roll_number', 'like', $q.'%') // '%'.
            ->where('rr.restlen', '>', 0)
            ->where('rr.user_id', $user_id)
            ->groupBy('rr.roll_number');
        $model->select(['rr.roll_number', 'rr.restlen']);
        $datas = $model->limit(10)->get();
        return ['status'=>'SUCCESS', 'data'=>$datas,
            $user_id,
            ];
    }
    /*
     * 获取省代简称、省代名称*/
    public function getSdmc(Request $request)
    {
        $user_id = $request->input('user_id');
        $mc = getDJc($user_id);
        return ['status'=>'SUCCESS', 'data'=>$mc];
    }
    public function user_sync_h(Request $request)
    {
        $data = $request->input('data');
        if (empty($data)) {
            abort(404);
        }
        $upload = [];
        foreach ($data as $v) {
            $upload[] = [
                'union_id' => $v['id'],
                'datas' => json_encode($v),
                'created_by' => session('admin.id'),
                'admin_name' => session('admin.username') ?: session('admin.unique_id'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }
        DB::table('users_sync')->insert($upload);
        /*$model = new UserSync();
        $model->admin_name = session('admin.username') ?: session('admin.unique_id');
        $model->created_by = session('admin.id');
        $model->datas = json_encode($data);
        $model->save();*/
        return ['status'=>'SUCCESS', 'msg' => '成功！'];

    }
    public function user_sync_test(Request $request)
    {
        $datas = DB::table('users')->where('user_type', '!=', 1)
            ->where('status', 1)
            // ->select(['id', 'abbr', 'creator_id', 'company_name', 'unique_id', 'first_name', 'status', ''])
            ->get();
        return ['status'=>'SUCCESS', 'data'=>$datas];
        return $datas;
    }
    public function getLog(Request $request)
    {
        $user_type = session()->get('admin.user_type');
        if ($user_type != 1) {
            abort(404);
        }
        $id = $request->input('id');
        $data = Activity::findOrFail($id);
        $data->abbr = getJc($data->user_id);
        return [
            'status' => 'SUCCESS',
            'data' => $data
        ];
    }
    /*
     * 根据卷号获取当前购买商
     * 发起调货时使用
     * 假如是管理员，仅显示已分配的用户
     * 假如是省代，仅显示下属经销商*/
    public function getRGMS(Request $request)
    {
        $user_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');
        $q = $request->input('roll_number');
        if ($q===null) {
            return ['status'=>'SUCCESS', 'data'=>[]];
        }
        $model = DB::table('roll_restlen as r')
            ->where('r.roll_number', $q)
            ->where('r.restlen', '>', 0)
            ->leftJoin('users as u', 'r.user_id', 'u.id');
        if ($user_type == 1) {
            $model = $model->where('r.user_id', '!=', 1);
        } else {
            $model = $model->whereIn('r.user_id', function ($query) use ($user_id) {
                $query->select('id')->from('users')
                    ->where('creator_id', $user_id);
            });
        }
        $datas = $model->select(['u.id', 'u.abbr', 'u.company_name', 'r.user_id'])
            ->limit(20)
            ->get();
        $res = [];
        foreach ($datas as $k => $v) {
            if (empty($v->id)) {
                continue;
            }
            $res[] = [
                'label' => $v->abbr ?: $v->company_name,
                'value' => $v->id,
            ];
        }
        return ['status'=>'SUCCESS', 'data'=>$res];
    }
    /*
     * 模糊查询卷号
     * 发起调货时使用
     * 假如是管理员，则模糊查询已分配的数据
     * 假如是非管理员，则模糊查询已分配给下属经销商的数据
     * 使用主表为roll_restlen*/
    public function getURRL(Request $request)
    {
        $user_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');
        $q = $request->input('q');
        $q = trim($q);
        if (empty($q)) {
            return ['status'=>'SUCCESS', 'data'=>[]];
        }
        $model = DB::table('roll_restlen')->where('roll_number', 'like', '%'.$q.'%')
            ->where('restlen', '>', 0)
            ->groupBy('roll_number');
        if ($user_type == 1) {
            $model = $model->where('user_id', '!=', 1);
        } else {
            $model = $model->whereIn('user_id', function ($query) use ($user_id) {
                $query->select('id')->from('users')
                    ->where('creator_id', $user_id);
            });
        }
        $datas = $model->limit(10)->get();
        return ['status'=>'SUCCESS', 'data'=>$datas,
            /*'sql'=>$model->toSql(), $user_id, $q*/
        ];
    }
    public function test1(Request $request)
    {
        // $w = Warranty::first();


        $w = getWVCByID(10096202);
        // $w = '[{"roll_number":"62805389","length":300,"part_id":19}]';
        // $w = json_decode($w, 1);
        // $w = $w->map(function ($value) {return (array)$value;})->toArray();
        $e = checkPartItems($w, 515);
        $d = (object) [['id'=>123]];
        return [$e, is_object($d), is_array($d)];

        return [$w];
        // checkPartItems
    }
    /*
     * 获取剩余长度
     * 传入卷号，如果不传用户ID，返回总剩余长度
     * 如果传入用户ID，返回用户剩余长度
     * 如果是在安装部位中使用，如果是质保，则使用传入用户ID
     * 如果是套餐，则使用登录用户ID*/
    public function getRestlen(Request $request)
    {
        $user_id = session('admin.id');
        $user_type = session('admin.user_type');
        if ($user_type == 1) {
            $user_id = 1;
        }
        $q = $request->input('q');
        if (empty($q)) {
            return ['status'=>false, 'msg'=>'没有找到卷号', 'error_code'=>1001];
        }
        $roll = RollMaster::where('roll_number', $q)->first();
        if (empty($roll)) {
            return ['status'=>false, 'msg'=>'没有找到卷号', 'error_code'=>1004];
        }
        $refer =$request->header('referer');
        if (empty($refer)) {
            return ['status'=>false, 'msg'=>'未知来源地址', 'data'=>['residue_length' => 0]];
        }
        $t = $request->input('t');
        $id = $request->input('id');
        if ($t == 'precut') {
            $p = DB::table('precut')->find($id);
            if (!empty($p) && !empty($p->user_id)) {
                $user_id = $p->created_by;
            } else {
            }
        }
        $model = DB::table('roll_restlen')->where([
            'user_id' => $user_id,
            'roll_number' => $q,
        ])->first();
        return [
            'status'=>'SUCCESS',
            'data'=>[
                'restlen'=>@$model->restlen,
                'film_type_id' => $roll->film_type_id,
                'width' => $roll->width,
            ],
        ];
    }
    /*
     * 获取用户
     * 套餐根据所选省代，返回经销商，并附带自己*/
    public function getUsers(Request $request)
    {
        $user_id = session('admin.id');
        $user_type = session('admin.user_type');
        $t = $request->input('t');
        $res = [];
        if ($t == 'd') {
            $user_id = $request->input('user_id');
            $model = Admin::where('status', 1)
                ->where(function ($query) use ($user_id) {
                    $query->where('id', $user_id)
                        ->orWhere('creator_id', $user_id);
                })
                ->orderBy('user_type')
                ->orderByDesc('id');
            $data = $model->get(['id', 'abbr', 'company_name']);
            foreach ($data as $k => $v) {
                $res[] = [
                    'label' => $v->abbr ?: $v->company_name,
                    'value' => $v->id,
                ];
            }
        }
        return ['status'=>'SUCCESS', 'data'=>$res];

    }

    public function phpinfo()
    {
        // return phpinfo();
    }
    public function getDealers(Request $request)
    {
        $creator_id = (int) $request->input('creator_id');
        $t = (int) $request->input('t');
        $users = getDealers($creator_id);
        $data = [];
        if ($t == 1) {
            $user = getUser($creator_id);
            if ($user->user_type == 2) {
                $data[] = [
                    'value' => $user->id,
                    'label' => $user->abbr,
                ];
            }
        }
        foreach ($users as $v) {
            $data[] = [
                'value' => $v->id,
                'label' => $v->abbr,
                'super_id' => $v->creator_id,
            ];
        }
        return [
            'status'=>'SUCCESS',
            'data' => $data
        ];
    }
    public function getUsersByType(Request $request)
    {
        $type = (int) $request->input('type');
        // 1省代2经销商，
        if ($type == 2) {
            $users = Admin::where([
                'user_type' => 2,
                'status' => 1,
            ])->get(['id', 'company_name']);
        } elseif ($type == 3) {
            $users = Admin::where([
                'user_type' => 3,
                'status' => 1,
            ])->get(['id', 'company_name']);
        } else {
            return ['status'=>false, 'msg'=>'未知用户'];
        }
        $data = [];
        foreach ($users as $k => $v) {
            $data[] = [
                'label' => $v->company_name,
                'value' => $v->id,
            ];
        }
        return ['status'=>'SUCCESS', 'data' => [
            'data' => $data
        ]];
    }
    public function handleWFQSH(Request $request)
    {
        $admin_id = session('admin.id');
        $user_type = session('admin.user_type');
        $id = $request->input('id');
        $w = Warranty::find($id);
        if (empty($w)) {
            return fs2('质保不存在！');
        }
        if ($user_type != 2) {
            return fs2('仅省代可以发起操作！');
        }
        if (empty($w->user_id)) {
            return fs2('尚未设置经销商！');
        }
        $user = Admin::withTrashed()->find($w->user_id);
        if (empty($user) || empty($user->id) || ($user->id != $admin_id && $user->creator_id != $admin_id)) {
            return fs2('无操作权限！');
        }
        if ($w->approved ==0) {
            return ['status'=>false, 'msg'=>'等待审核中，请不要重复提交'];
        }
        if ($w->approved ==1) {
            return ['status'=>false, 'msg'=>'信息已通过，请不要重复提交'];
        }
        if ($w->approved ==2) {
            return ['status'=>false, 'msg'=>'信息被拒绝，请修改后提交'];
        }
        $alogs = alogs('质保', '发起审核', $w->id, null, $w);
        $status = checkWarrantyV3($id);
        if (@$status['status'] !== 'SUCCESS') {
            return $status;
        }
        $model = WarrantyVerify::firstOrNew(['warranty_id'=>$id, 'approved'=>0]);
        $model->created_by = $admin_id;
        $model->approved = 0;
        $model->save();

        $w->fq_user = $admin_id;
        $w->fq_date = date('Y-m-d H:i:s');
        $w->approved = 0;
        $w->save();
        $alogs->new = $w;
        $alogs->save();

        session()->flash('hightlight', ['id'=>$id, 'type'=>'warranty']);
        return ['status'=>'SUCCESS', $model];
    }


    public function index(Request $request, $action)
    {
        \Debugbar::disable();
        $do = $action;
        $method = $request->method();
        $method = strtolower($method);
        if ($method=='get') {
            if (in_array($do, $this->get)) {
                return $this->$do($request);
            }
        }
        if ($method=='delete') {
            if (in_array($do, $this->delete)) {
                return $this->$do($request);
            }
        }
        if ($method=='put') {
            if (in_array($do, $this->put)) {
                return $this->$do($request);
            }
        }
        if ($method=='post') {
            if (in_array($do, $this->post)) {
                return $this->$do($request);
            }
            if (in_array($do, $this->delete)) {
                return $this->$do($request);
            }
            if (in_array($do, $this->put)) {
                return $this->$do($request);
            }
        }
        return [$do];
    }

    /*
     * 新版上传，图片保存在七牛云*/
    public function upload_file(Request $request)
    {
        $file = $request->file('file');
        if (empty($file)) {
            return ['status'=>4001, 'name'=>'文件不存在'];
        }
        $cmt = $file->getClientMimeType();
        $ext = '';
        if (stripos($cmt, 'image/') === 0) {
            $ext = substr($cmt,6);
        }
        if (empty($ext)) {
            return ['status'=>false, 'msg'=>'仅支持上传图片格式'];
        } else {
            $ext = '.'.$ext;
        }
        $fileName = Str::random(32).$ext;

        $accessKey = env('QINIUYUN_ACCESS_KEY');
        $secretKey = env('QINIUYUN_SECRET_KEY');
        $auth = new Auth($accessKey, $secretKey);
        $bucket = 'warranty-xpel';
        $uploadMgr = new UploadManager();
        $token = $auth->uploadToken($bucket);
        list($ret, $error) = $uploadMgr->putFile($token, $fileName, $file);
        if ($error !== null) {
            return ['status'=>false, 'msg'=>'图片上传失败'];
        }
        $host = env('QINIUYUN_SECRET_HOST');
        return ['status'=>'SUCCESS', 'data' => [
            'url'=>$ret['key'],
            'host' => $host
        ]];
    }
    /*
     * 旧版上传*/
    public function upload_file_OLD(Request $request)
    {
        $file = $request->file('file');
        if (empty($file)) {
            return ['status'=>4001, 'name'=>'文件不存在'];
        }
        $ext = ["png", "jpg", "jpeg", "gif", "bmp"];
        $e = $file->getClientOriginalExtension();

        $pname = '/upload/'.date('Ym').'/';
        $basePath = public_path($pname);
        if (!file_exists($basePath)) {
            mkdir($basePath, 0755, true);
        }

        if (!in_array($e, $ext)) {
            $e = 'txt';
            $name = Str::random(16).'.'.$e;
            $bool = $file->storeAs($pname, $name);
            return ['status'=>false, 'msg'=>'图片错误！'];
        }
        $name = Str::random(16).'.'.$e;
        $bool = $file->storeAs($pname, $name);
        return ['status'=>'SUCCESS', 'data' => [
            'url'=>$pname.$name,
        ]];
    }

    public function checkDistributorUniqueId(Request $request)
    {
        $unique_id = trim($request->input('unique_id'));
        /*if (mb_strlen($unique_id) != 7) {
            return ['status'=>false, 'msg' => '账号长度限制为7位'];
        }*/
        $ulen = mb_strlen($unique_id);
        $pm = '/^CN[0-9]{4,7}$/';
        // $unique_id = str_replace(' ', '', $unique_id);
        // $unique_id = strtoupper($unique_id);

        if (!preg_match($pm, $unique_id)) {
            return ['status' => false, 'msg' => '账号不符合规则！账号规则为CN+（4-7）位数字组成！'];
        }
        if (empty($unique_id)) {
            return ['status' => false, 'msg' => '账号不能为空！'];
        }
        if ($ulen < 6 || $ulen >9) {
            return ['status' => false, 'msg' => '账号长度不符合！'];
            // return redirect()-> back()->withInput()->with('trash', ['type'=>'error', 'content'=>'账号长度不符合！']);
        }
        $mod = Admin::where(['unique_id' => $unique_id])->withTrashed()->get();
        if (count($mod) >= 1) {
            return ['status' => false, 'msg' => ts('Duplicate Records') . ' !'];
        }
        return ['status' => 'SUCCESS', 'msg' => '账号目前仍可以使用，请尽快注册！'];
    }

    public function coverages_json_data(Request $request)
    {
        $lang = $this->getLangName($request);
        $parts = PartMaster::orderByDesc('id')->get(['id', 'product_type_id', 'min_length', $lang]);
        $film_types = FilmTypeMaster::orderByDesc('id')->get(['id', 'product_type_id', $lang]);
        $widths = [61,76,92,152];
        $part = [];
        foreach ($parts as $k => $v) {
            $part[] = [
                'label' => $v->$lang,
                'value' => $v->id,
                'product_type_id' => $v->product_type_id,
                'min_length' => $v->min_length,
            ];
        }
        $filmtype = [];
        foreach ($film_types as $k => $v) {
            $filmtype[] = [
                'label' => $v->$lang,
                'value' => $v->id,
                'product_type_id' => $v->product_type_id,
            ];
        }
        $width = [];
        foreach ($widths as $k => $v) {
            $width[] = [
                'label' => $v,
                'value' => $v,
            ];
        }
        $data = [
            'part' => $part,
            'filmtype' => $filmtype,
            'width' => $width,
        ];
        return 'window.coverages_data='.json_encode($data);
    }

    protected function getRollsMasterByRollNumber($roll_number)
    {
        return DB::table('roll_master')->where('roll_number', $roll_number)->first();
    }
    /*
     * 仅获取自身使用的长度
     * */
    protected function getUserUsedLengthByRollNumber($roll_number, $user_id)
    {
        $w = DB::table('warranty_to_vehicle_coverage')
            ->leftJoin('warranty', 'warranty.id', 'warranty_to_vehicle_coverage.warranty_id')
            ->where('roll_number', $roll_number)
            ->select('length')
            ->get();

        $p = DB::table('precut as p')
            ->leftJoin('precut_to_vehicle_coverage as pvc', 'pvc.precut_kit_sale_id', '=', 'p.id')
            ->where('roll_number', $roll_number)
            ->where('p.status', '!=', 1)
            ->select('length')
            ->get();
        return array_sum(Arr::pluck($w, 'length')) + array_sum(Arr::pluck($p, 'length'));
    }

    public function getResidueLengthByRollNumberAndGmsId(Request $request)
    {
        $roll_number = $request->input('roll_number');
        $user_id = $request->input('gmsid');
        if (empty($roll_number) || empty($user_id)) {
            return ['status'=>false, 'msg'=>'参数为空！'];
        }
        $model = RollRestlen::firstOrNew([
            'user_id'=>$user_id,
            'roll_number' => $roll_number
        ]);
        return [
            'status'=>'SUCCESS',
            'data'=> $model->restlen,
        ];
    }
    public function findGMSByRN(Request $request)
    {
        $q = $request->input('roll_number');
        if ($q===null) {
            return ['status'=>'SUCCESS', 'data'=>[]];
        }
        $datas = DB::table('roll_master as rm')
            ->leftJoin('roll_sales', 'roll_sales.roll_id', '=', 'rm.id')
            ->leftJoin('users', 'users.id', '=', 'roll_sales.sold_to_user_id')
            ->where('rm.roll_number', $q)
            ->whereNull('rm.deleted_at')
            ->select(['users.id', 'users.abbr', 'users.company_name', 'roll_sales.sold_to_user_id'])
            ->limit(1000)
            ->get();
        if (count($datas) < 1) {
            return ['status'=>false, 'data'=>ts('JHBCZ')];
        }
        $admin = DB::table('users')->find(1);
        $res = [];
        foreach ($datas as $k => $v) {
            if (empty($v->sold_to_user_id)) {
                continue;
            }
            $res[$v->sold_to_user_id] = [
                'label' => $v->abbr ?: $v->company_name,
                'value' => $v->sold_to_user_id,
            ];
        }
        $res = array_values($res);
        $res[] = [
            'label' => $admin->abbr ?: $admin->company_name,
            'value' => $admin->id,
        ];

        return ['status'=>'SUCCESS', 'data'=>$res];
    }

    public function getGMSByRollNumber(Request $request)
    {
        $q = $request->input('roll_number');
        if ($q===null) {
            return ['status'=>'SUCCESS', 'data'=>[]];
        }
        $datas = DB::table('roll_sales')
            ->leftJoin('roll_master', 'roll_sales.roll_id', '=', 'roll_master.id')
            ->leftJoin('users', 'users.id', '=', 'roll_sales.sold_to_user_id')
            ->where('roll_master.roll_number', 'like', '%'.$q.'%')
            ->select(['users.id', 'users.abbr', 'users.company_name', 'roll_sales.sold_to_user_id'])
            ->limit(20)
            ->get();
        $admin = DB::table('users')->find(1);
        $res = [];
        foreach ($datas as $k => $v) {
            if (empty($v->sold_to_user_id)) {
                continue;
            }
            $res[] = [
                'label' => $v->abbr ?: $v->company_name,
                'value' => $v->sold_to_user_id,
            ];
        }
        $res[] = [
            'label' => $admin->abbr ?: $admin->company_name,
            'value' => $admin->id,
        ];

        return ['status'=>'SUCCESS', 'data'=>$res];
    }

    public function searchAllRollsByRollNumber(Request $request)
    {
        $q = $request->input('q');
        $film_type_id = $request->input('film_type_id');
        if ($q===null || $film_type_id===null) {
            return ['status'=>'SUCCESS', 'data'=>[]];
        }
        $datas = DB::table('roll_master')
            ->where('roll_number', 'like', '%'.$q.'%')
            ->where('film_type_id', '=', $film_type_id)
            ->limit(20)
            ->get();
        return ['status'=>'SUCCESS', 'data'=>$datas];
    }
    /*
     * 查询可用卷号
     * */
    public function searchRollsByRollNumber(Request $request)
    {
        $user_id = session('admin.id');
        $user_type = session('admin.user_type');
        $q = $request->input('q');
        $p = $request->input('pid');
        $refer =$request->header('referer');
        if (empty($refer)) {
            return ['status'=>false, 'msg'=>'未知来源', 'data'=>['residue_length' => 0]];
        }
        $parse_url = parse_url($refer);
        $rpath = @$parse_url['path']; // 地址
        if (stripos ($rpath, '/admin/warranty/')===0) {
            $user_id = $request->input('user_id');
            $source = 'warranty';
            $id = str_replace('/admin/warranty/', '', $rpath);
            $id = str_replace('/edit', '', $rpath);
            $id = (int) $id;
        }
        if (stripos ($rpath, '/admin/precut/')===0) {
            $user_id = session('admin.id');
            if ($user_type == 1) {
                $user_id = 1;
            }
            $source = 'precut';
            $id = str_replace('/admin/precut/', '', $rpath);
            $id = str_replace('/edit', '', $rpath);
            $id = (int) $id;
        }
        if ($q===null) {
            return ['status'=>false, 'msg'=>'没有找到卷号', 'data'=>['residue_length' => 0]];
        }
        if (empty($user_id)) {
            return ['status'=>false, 'msg'=>'没有找到用户', 'data'=>['residue_length' => 0]];
        }
        $user = Admin::findOrFail($user_id);

        $model = DB::table('roll_master');
        if ($user->user_type == 1) {

        } else {
            /*$model = $model->whereIn('id', function ($query) use ($user_id) {
                $query->select('roll_sales.roll_id')->from('roll_sales')
                    ->leftJoin('users', 'roll_sales.sold_to_user_id', '=', 'users.id')
                    ->where('users.id', $user_id)
                    ->orWhere('users.creator_id', $user_id)
                    ->groupBy('roll_sales.roll_id');
            });*/
        }
        $model = $model->whereIn('roll_number', function ($query) use ($user_id) {
            $query->select('roll_restlen.roll_number')->from('roll_restlen')
                ->leftJoin('users', 'roll_restlen.user_id', '=', 'users.id')
                // ->leftJoin('film_type_master as fm', 'roll_number.film_type_id', '=', 'fm.id')
                ->where('users.id', $user_id)
                ->where('roll_restlen.restlen', '>', 0)
                ->groupBy('roll_restlen.roll_number');
        });
        if ($p!==null) {
            $model = $model->where('roll_master.product_type_id', $p);
        }
        $datas = $model->where('roll_number', 'like', '%'.$q.'%')
            ->whereNull('deleted_at')
            ->select(['roll_number', 'width', 'film_type_id', 'product_type_id'])
            ->limit(8)
            ->get();
        return ['status'=>'SUCCESS', 'data'=>$datas, $user_id];
    }
    // all
    public function getPrecutUsedLengthByRollNumber2($roll_number, $user_id=0, $type=1)
    {

    }
    // not install，
    public function getPrecutUsedLengthByRollNumber($roll_number, $user_id=0, $type=1)
    {

    }
    /*
     * $user_id为真时，查询该用户使用的数据，
     * $type=2时，查询该用户作为省代，自己及下属使用的该卷号数据
     * */
    public function getCoverageUsedLengthByRollNumber($roll_number, $user_id=0, $type=1)
    {
        $model = DB::table('warranty_to_vehicle_coverage');
        if ($user_id) {

        }

    }
    // 获取的是当前登录用户的数据
    /*
     * 质保信息表，仅允许质保与套餐创建及修改时使用
     * 根据传入的用户ID和卷号，查询用户该卷号剩余长度
     * 假如来源是套餐表，则查询的是登录用户的信息
     * */
    public function getResidueLengthForCoverageByRollNumber(Request $request)
    {
        $user_type = session('admin.user_type');
        $q = $request->input('q');
        $refer =$request->header('referer');
        if (empty($refer)) {
            return ['status'=>false, 'msg'=>'未知来源', 'data'=>['residue_length' => 0]];
        }
        $parse_url = parse_url($refer);
        $rpath = @$parse_url['path']; // 地址
        if (stripos ($rpath, '/admin/warranty/')===0) {
            $user_id = $request->input('user_id');
            $source = 'warranty';
            $id = str_replace('/admin/warranty/', '', $rpath);
            $id = str_replace('/edit', '', $rpath);
            $id = (int) $id;
        }
        if (stripos ($rpath, '/admin/precut/')===0) {
            $user_id = session('admin.id');
            $source = 'precut';
            $id = str_replace('/admin/precut/', '', $rpath);
            $id = str_replace('/edit', '', $rpath);
            $id = (int) $id;
        }
        if ($q===null) {
            return ['status'=>false, 'msg'=>'没有找到卷号', 'data'=>['residue_length' => 0]];
        }
        if (empty($user_id)) {
            return ['status'=>false, 'msg'=>'没有找到用户', 'data'=>['residue_length' => 0], $rpath];
        }
        $roll = RollMaster::withTrashed()->where('roll_number', $q)->firstOrFail();
        // $roll = RollMaster::withTrashed()->where('roll_number', $q)->firstOrFail();
        $len = getRestLen($roll->roll_number, $user_id);
        $ulen = 0;
        if ($id) {
            if ($source == 'warranty') {
                $ulen = getWTLenByRN($id, $q);
            } elseif ($source == 'precut') {
                $ulen = getPSTLenByRN($id, $q);
            }
        }

        return [
            'status'=>'SUCCESS',
            // , 'ulen' => $ulen
            'data'=>['residue_length' => $len + $ulen],
        ];
    }

    // 计算roll_sales表中，用户出售与购买的长度，如果没有给定用户ID，用户为登录用户
    public function getUserAssignResidueLengthByRollId($id, $user_id=0)
    {
        $user_id = $user_id ?: session('admin.id');
        $user_type = session('admin.user_type');
        $roll = RollMaster::withTrashed()->findOrFail($id);
        $sold_to = 0; // 分配给用户的长度
        $sold_by = 0; // 用户分配出去的长度
        $rolls = DB::table('roll_sales')->where('roll_id', $id)->get();
        foreach ($rolls as $k => $v) {
            if ($v->sold_by_user_id == $user_id) {
                $sold_by += $v->length;
            }
            if ($v->sold_to_user_id == $user_id) {
                $sold_to += $v->length;
            }
        }
        // 管理员初始化有所有长度
        if ($user_id == 1) {
            $sold_to += $roll->length;
        }
        return $sold_to - $sold_by;
    }

    // 用于分配，仅查询分配出去的与剩余的长度，不计算使用长度
    public function getResidueLengthByRollNumber(Request $request)
    {
        $user_id = session('admin.id');
        $user_type = session('admin.user_type');
        $q = $request->input('q');
        if ($q===null) {
            return ['status'=>false, 'msg'=>'没有找到卷号', 'data'=>['residue_length' => 0]];
        }
        $model = DB::table('roll_restlen')
            ->where('roll_number', $q);
        if ($user_type == 1) {
            $model = $model->where('user_id', 1);
        } else {
            $model = $model->where('user_id', $user_id);
        }
        $roll = $model->first();
        if (empty($roll) || empty($roll->id)) {
            return ['status'=>false, 'msg'=>'没有找到卷号', 'data'=>['residue_length' => 0]];
        }

        return [
            'status'=>'SUCCESS',
            'data' => [
                'residue_length' => $roll->restlen,
            ],
        ];

        $q = $request->input('q');
        if ($q===null) {
            return ['status'=>false, 'msg'=>'没有找到卷号', 'data'=>['residue_length' => 0]];
        }
        // getRestLen($q, $user_id);

        $roll = DB::table('roll_master')->where('roll_number', $q)->first();
        // $residue_length = getUserResidueAssignLengthByRollId($roll->id, $user_id);
        // $residue_length = getUserCanAssignLengthByRollId($roll->id, $user_id);
        $residue_length = getRestLen($roll->roll_number, $user_id);
        return [
            'status'=>'SUCCESS',
            'data' => [
                'id' => $roll->id,
                'width' => $roll->width,
                'film_type_id' => $roll->film_type_id,
                'length' => $roll->length,
                'residue_length' => $residue_length,
            ],
        ];
    }

    public function make_json_data(Request $request)
    {
        $language = (int) session('language') ?: 1;
        if ($language == 1) {
            $lang = 'english_value';
        } elseif ($language == 2) {
            $lang = 'traditional_chiness_value';
        } elseif ($language == 3) {
            $lang = 'simplified_chiness_value';
        } else {
            $lang = 'english_value';
        }
        $datas = MakeMaster::get(['id', 'year_id', $lang]);
        $year = $make = [];
        foreach ($datas as $k => $v) {
            $year[$v->year_id] = 1;
            $make[] = [
                'label' => $v->$lang,
                'value' => $v->id,
                'year_id' => $v->year_id,
            ];
        }
        $year = array_keys($year);
        sort($year);
        $years = [];
        foreach ($year as $k => $v) {
            $years[] = [
                'label' => $v,
                'value' => $v,
            ];
        }

        $data = [
            'year' => $years,
            'make' => $make,
        ];
        return 'window.makes_data='.json_encode($data);
    }
    public function regions_json_data(Request $request)
    {
        $language = (int) session('language') ?: 1;
        if ($language == 1) {
            $lang = 'english_value';
        } elseif ($language == 2) {
            $lang = 'traditional_chiness_value';
        } elseif ($language == 3) {
            $lang = 'simplified_chiness_value';
        } else {
            $lang = 'english_value';
        }
        $region = RegionMaster::get();
        $province = ProvinceMaster::get();
        $city = Cities::get();
        $region_data = $province_data = $city_data = [];
        foreach ($region as $k => $v) {
            $item = [
                'label' => $v->$lang,
                'value' => $v->id,
            ];
            $region_data[] = $item;
        }
        foreach ($province as $k => $v) {
            $item = [
                'label' => $v->$lang,
                'region_id' => $v->region_id,
                'value' => $v->id,
            ];
            $province_data[] = $item;
        }
        foreach ($city as $k => $v) {
            $item = [
                'label' => $v->$lang,
                'province_id' => $v->province_id,
                'value' => $v->id,
            ];
            $city_data[] = $item;
        }
        $data = [
            'region' => $region_data,
            'province' => $province_data,
            'city' => $city_data,
        ];
        return 'window.regions_data='.json_encode($data);
    }

    public function lang_change(Request $request)
    {
        $language = (int) $request->input('lang');
        if ($language == 1) {
            App::setLocale('en');
            session(['language'=>1]);
        } elseif ($language == 2) {
            App::setLocale('zh_tw');
            session(['language'=>2]);
        } elseif ($language == 3) {
            App::setLocale('zh');
            session(['language'=>3]);
        } else {
            App::setLocale('zh');
            session(['language'=>3]);
        }
        return ['status'=>'SUCCESS'];
    }

    public function getProvinceByID(Request $request)
    {
        $language = $this->getLanguageID();
        $id = (int) $request->input('region_id');
        $data = ProvinceMaster::where('region_id', $id)->get(['id', $this->language_column]);
        return [
            'status' => 'SUCCESS',
            'data' => $data
        ];
    }
    public function getcityByID(Request $request)
    {
        $language = $this->getLanguageID();
        $id = (int) $request->input('province_id');
        $data = Cities::where('province_id', $id)->get(['id', $this->language_column]);
        return [
            'status' => 'SUCCESS',
            'data' => $data
        ];
    }

    protected function getLangName()
    {
        $language = (int) session('language') ?: 1;
        if ($language == 1) {
            $lang = 'english_value';
        } elseif ($language == 2) {
            $lang = 'traditional_chiness_value';
        } elseif ($language == 3) {
            $lang = 'simplified_chiness_value';
        } else {
            $lang = 'english_value';
        }
        return $lang;
    }
}
