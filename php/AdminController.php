<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\UserSync;
use App\Models\PartMaster;
use App\Models\RollMaster;
use App\Models\RollSales;
use App\Models\RollTransfer;
use App\Models\Sequences;
use App\Models\Warranty;
use App\Models\WarrantyMsg;
use App\Models\WarrantyImage;
use App\Models\FileUpload;
use App\Models\RollRestlen;
use App\Models\RollReturn;
use App\Models\Precut;
use App\Models\PrecutAssign;
use App\Models\WarrantyVerify;

use App\Models\WarrantyToVehicleCoverage;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AdminController extends Controller
{
    public function user_sync(Request $request)
    {
        checkAuthority(1);
        $page = [
            'page_name' => 'monitor',
            'title' => ts('JXSTB'),
            'breadcrumb' => [
                [
                    'text' => ts('MONITORINGCENTER'),
                    'url' => 'javascript:void(0)'
                ]
            ],
        ];
        $model = new UserSync();
        // $model = DB::table('users_sync');
        $model = filterByDateRange($model, 'created_at');
        $model = filterByColumns($model, ['union_id', 'user_id', 'datas']);
        $datas = $model->orderByDesc('id');
        $datas = $model->paginate();
        return view('admin.admin.user-sync', [
            'data'=> $datas,
            'page' => $page,
        ]);

    }

    public function user_sync_show(Request $request, $id)
    {
        checkAuthority(1);
        $data = UserSync::findOrFail($id);
        $page = [
            'page_name' => 'monitor',
            'title' => ts('JXSTBxq'),
            'breadcrumb' => [
                [
                    'text' => ts('JXSTB'),
                    'url' => '/admin/user_sync'
                ]
            ],
        ];
        $admin = $data->user();
        $obj = json_decode($data->datas);
        $obj = $obj ?: (object)[];
        return view('admin.admin.user-sync-show', [
            'data'=> $data,
            'page' => $page,
            'admin' => $admin,
            'obj' => $obj
        ]);
    }

    public function user_sync_update(Request $request, $id)
    {
        checkAuthority(1);
        $datas = UserSync::findOrFail($id);
        if (empty($datas->user_id)) {
            $admin = new Admin();
        } else {
            $admin = Admin::findOrNew($datas->user_id);
        }
        $alogs = alogs('经销商同步', '同步', $datas->union_id, null, $admin);
        $obj = json_decode($datas->datas);
        $obj = $obj ?: (object)[];
        if (empty($obj)) {
            return ['status'=>false, 'msg'=>"用户资料为空！"];
        }
        if (!in_array($obj->user_type, [2, 3])) {
            abort(404);
        }
        $admin->user_type = $obj->user_type;
        $admin->abbr = $obj->abbr;
        $admin->company_name = $obj->company_name;
        $admin->first_name = $obj->first_name;
        $admin->email_address = $obj->email_address;
        $admin->phone_number = $obj->phone_number;
        $admin->cell_phone = $obj->cell_phone;
        $admin->wechat_id = $obj->wechat_id;
        $admin->region_id = $obj->region_id;
        $admin->province_id = $obj->province_id;
        $admin->city = $obj->city;
        $admin->note = $obj->note;
        $admin->status = (int) $obj->status;
        if ($obj->user_type == 2) {
            $admin->creator_id = 1;
            $admin->unique_id = $admin->unique_id ?: $obj->unique_id;
            $admin->password = $admin->password ?: $obj->password;
            if (empty($admin->unique_id) || empty($admin->password)) {
                return ['status'=>false, 'msg'=>"省代登录用户名或密码不能为空！"];
            }
            $m = DB::table('users')->where('unique_id', $admin->unique_id)->first();
            if ($m->id != $admin->id) {
                return ['status'=>false, 'msg'=>"数据重复！"];
            }
        } elseif ($obj->user_type == 3) {
            $admin->creator_id = gUDId($obj->creator_id) ?: $obj->creator_id; // 需要根据唯一ID查询省代ID
            $admin->unique_id = $admin->unique_id ?: Str::random(64);
            $admin->password = $admin->password ?: Str::random(64);
        }

        $admin->save();
        $datas->user_id = $admin->id;
        $datas->status = 1;
        $datas->save();
        $alogs->new = $admin;
        $alogs->save();

        return ['status'=>'SUCCESS', 'msg'=>'成功！'];

    }

    public function userinfo(Request $request)
    {
        $page = [
            'page_name' => 'userinfo',
            'title' => ts('grzx'),
        ];
        $admin_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');
        /*if ($user_type == 1) {
            abort(404);
        }*/
        $model = Admin::findOrFail($admin_id);
        return view('admin.admin.userinfo', ['data'=>$model, 'page'=>$page]);
    }

    public function userinfo_edit(Request $request)
    {
        $admin_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');
        if ($user_type == 1) {
            abort(404);
        }
        $page = [
            'page_name' => 'userinfo',
            'title' => ts('edit'),
            'breadcrumb' => [
                [
                    'text' => ts('grzx'),
                    'url' => '/admin/userinfo'
                ]
            ],

        ];
        $admin_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');
        $model = Admin::findOrFail($admin_id);
        return view('admin.admin.userinfo-edit', ['data'=>$model, 'page'=>$page]);
    }
    public function userinfo_update(Request $request)
    {
        $admin_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');
        if ($user_type == 1) {
            abort(404);
        }
        $model = Admin::findOrFail($admin_id);

        $alogs = alogs('省代', '修改', $model->id, null, $model);

        $model->email_address = $request->input('email_address');
        $model->address = $request->input('address');
        $model->first_name = $request->input('first_name');
        $model->cell_phone = $request->input('cell_phone');
        $model->wechat_id = $request->input('wechat_id');
        $model->save();
        $alogs->new = $model;
        $alogs->save();
        return redirect('/admin/userinfo');
    }
    public function userinfo_change_pwd(Request $request)
    {
        $admin_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');
        $name = '省代';
        if ($user_type == 1) {
            $name = '管理员';
        }
        $model = Admin::findOrFail($admin_id);
        $pwd = trim($request->input('pwd'));
        $pwd2 = trim($request->input('pwd_r'));
        if (empty($pwd)) {
            return ['status'=>false, 'msg'=>'密码不能为空！'];
        }
        if ($pwd != $pwd2) {
            return ['status'=>false, 'msg'=>'两次密码输入不一致！'];
        }
        $alogs = alogs($name, '修改密码', $model->id, ['password' => $pwd], ['password' => $model->password]);
        $model->password = $pwd;
        $model->save();
        return ['status'=>'SUCCESS', 'msg' => '成功！'];
    }

    /*
     * 安装数量，按地区划分*/
    protected function chartA1(Request $request)
    {
        $user_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');
        $year = $request->input('year');
        $year = $year ?: date('Y');
        $r = DB::table('region_master')->orderBy('id')->get('id');
        $region = Arr::pluck($r, 'id'); // 地区
        $tm = []; // 补零后的1-12
        foreach (range(1,12) as $v) {
            $k = str_pad($v, 2, 0, STR_PAD_LEFT);
            $tm[$k] = 0;
        }
        $c1 = []; // 安装数量，按地区划分
        if ($user_type == 1) {
            $data = DB::select("SELECT date_format(installation_date,'%m') AS oaWonVEI,COUNT(id) AS tX1xYm5Q, region_id FROM warranty WHERE installation_date like '".$year."%' GROUP BY region_id,oaWonVEI");
        } elseif ($user_type == 2) {
            $data = DB::select("SELECT date_format(installation_date,'%m') AS oaWonVEI,COUNT(id) AS tX1xYm5Q, region_id FROM warranty WHERE installation_date like '".$year."%' and user_id in (select id from users where id='".$user_id."' or creator_id='".$user_id."') GROUP BY region_id,oaWonVEI");
        }
        foreach ($region as $k => $v) {
            $c1[$v] = $tm; // 地区
        }
        foreach ($data as $k => $v) {
            if (empty($c1[$v->region_id])) {
                $c1[$v->region_id] = [];
            }
            $c1[$v->region_id][$v->oaWonVEI] = $v->tX1xYm5Q;
        }
        $c1_1 = [
            'name' => ts('region_master.1'),
            'type' => 'line',
            'color' => '#1891ff',
            'data' => array_values($c1[1]),
        ];
        $c1_2 = [
            'name' => ts('region_master.2'),
            'type' => 'line',
            'color' => '#f9cc14',
            'data' => array_values($c1[2]),
        ];
        $c1_3 = [
            'name' => ts('region_master.3'),
            'type' => 'line',
            'color' => '#13c2c2',
            'data' => array_values($c1[3]),
        ];
        $c1_4 = [
            'name' => ts('region_master.4'),
            'type' => 'line',
            'color' => '#2fc25b',
            'data' => array_values($c1[4]),
        ];
        $c1_xAxis = $this->getMon1();
        $c1_legend = [ts('region_master.1'),ts('region_master.2'),ts('region_master.3'),ts('region_master.4')];
        return [
            'series' => [
                $c1_1,
                $c1_2,
                $c1_3,
                $c1_4,
            ],
            'title' => ts('AZSL'),
            'legend' => $c1_legend,
            'xAxis' => $c1_xAxis,
        ];
    }
    protected function chartA2(Request $request)
    {

    }

    protected function sd_index(Request $request)
    {
        $user_id = session('admin.id');
        $jxs = DB::table('users')->where('status', 1)
            ->where('creator_id', $user_id)
            ->where('user_type', 3)->count();
        $kh = DB::table('warranty')->where('approved', 1)
            ->whereIn('user_id', function ($query) use ($user_id) {
                $query->select('id')->from('users')
                    ->where('id', $user_id)
                    ->orWhere('creator_id', $user_id);
            })
            ->count();
        $dh = DB::table('roll_transfer')->where('approved', 1)->count();
        $zb = DB::table('precut')->where('status', 0)
            ->whereIn('user_id', function ($query) use ($user_id) {
                $query->select('id')->from('users')
                    ->where('id', $user_id)
                    ->orWhere('creator_id', $user_id);
            })
            ->count();

        $panel = [
            [
                'title' => ts('dealer'),
                'no' => $jxs,
                'icon' => 'fa-user',
                'url' => '/admin/dealer?status=1',
            ],
            [
                'title' => ts('AZSL'),
                'no' => $kh,
                'icon' => 'fa-wrench',
                'url' => '/admin/warranty?status=1',
            ],
            [
                'title' => ts('DANTC'),
                'no' => $zb,
                'icon' => 'fa-cogs',
                'url' => '/admin/precut_deal',
            ],
            [
            ],
        ];
        return [
            'panel' => $panel
        ];

    }
    protected function gly_index(Request $request)
    {

        $um = DB::table('users')->where('status', 1);
        $sd = DB::table('users')->where('status', 1)->where('user_type', 2)->count();
        $jxs = DB::table('users')->where('status', 1)->where('user_type', 3)->count();
        $kh = DB::table('warranty')->where('approved', 1)->count();
        $dh = DB::table('roll_transfer')->where('approved', 0)->count();
        $zb = DB::table('warranty_verify')->where('approved', 0)->count();

        $wdea = DB::select("select count(w.id), u.creator_id as user_id from warranty as w left join users as u on u.id=w.user_id WHERE u.user_type=3 group by u.creator_id");
        $wdis = DB::select("select count(w.id), user_id from warranty as w left join users as u on u.id=w.user_id WHERE u.user_type!=3 group by u.id");
        $wuser = DB::table('users')->where('user_type', '!=', 3)->get(['id', 'abbr']);
        $uab = [];
        foreach ($wuser as $k => $v) {
            $v->count = 0;
            $uab[$v->id] = $v;
        }

        $panel = [
            [
                'title' => ts('distributor'),
                'no' => $sd,
                'icon' => 'fa-user',
                'url' => '/admin/distributor?status=1',
            ],
            [
                'title' => ts('dealer'),
                'no' => $jxs,
                'icon' => 'fa-user',
                'url' => '/admin/dealer?status=1',
            ],
            [
                'title' => ts('AZSL'),
                'no' => $kh,
                'icon' => 'fa-wrench',
                'url' => '/admin/warranty?status=1',
            ],
            [
                'title' => ts('DCLDH'),
                'no' => $dh,
                'icon' => 'fa-cogs',
                'url' => '/admin/verify/transfer?status=0',
            ],
            [
                'title' => ts('DCLZB'),
                'no' => $zb,
                'icon' => 'fa-cogs',
                'url' => '/admin/verify/warranty?status=0',
            ],
        ];
        return [
            'panel' => $panel,
        ];
    }

    public function index(Request $request)
    {
        $user_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');
        $chart = [];
        if ($user_type == 1) {
            $data = $this->gly_index($request);
            $chart = $this->charts_index($request);
            $chart = $chart['charts'];
        } elseif ($user_type == 2) {
            $data = $this->sd_index($request);
            $chart = [
                'c1' => $this->chartA1($request),
            ];
        } else {

            $data = $this->gly_index($request);
            $chart = $this->charts_index($request);
            $chart = $chart['charts'];
            
            // abort(404);
        }
        $page = [
            'page_name' => 'distributor',
            'title' => ts('MANAGEDISTRIBUTOR')
        ];


        return view('admin.index.index', [
            'data'=>$data,
            'page' => $page,
            'charts' => $chart,
        ]);
    }
    public function distributor_index_o(Request $request)
    {
        checkAuthority();
        $page = [
            'page_name' => 'distributor-o',
            'title' => ts('CSSDGL')
        ];
        $model = Admin::where('user_type', 2)->where('status', '!=', 2);
        $model = filterByColumns($model, ['unique_id', 'abbr', 'company_name','first_name','phone_number', 'first_name']);
        $status = (int) $request->input('status');
        if ($status == 1) {
            $model = $model->onlyTrashed();
        } elseif ($status == 2) {
            $model = $model->where('status', '!=', 1);
        } else {
            $model = $model->withTrashed()->where(function ($query) {
                $query->whereNotNull('deleted_at')
                    ->orWhere('status', '!=', 1);
            });
        }
        $data = $model->orderByDesc('id')->paginate();
        return view('admin.admin.distributor-index-o', [
            'data'=>$data,
            'page' => $page,
        ]);
    }

    public function distributor_index(Request $request)
    {
        checkAuthority();
        $page = [
            'page_name' => 'distributor',
            'title' => ts('MANAGEDISTRIBUTOR')
        ];
        $model = Admin::where([
            'user_type' => 2
        ]);
        $model = $this->filterByColumns($request, $model, ['unique_id', 'abbr', 'company_name','first_name','phone_number', 'first_name']);
        if ($request->input('status')!==null) {
            $status = (int) $request->input('status');
            if ($status != 1) {
                $status = 0;
            }
            $model = $model->where('status', $status);
        }
        // $model = $this->orderByColumn($request, $model, ['id', 'unique_id','company_name','first_name','phone_number','status']);
        $data = $model->orderBy('unique_id')->paginate();
        return view('admin.admin.distributor-index', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    public function distributor_show(Request $request, $id)
    {
        checkAuthority();
        $page = [
            'page_name' => 'distributor',
            'title' => ts('DISTRIBUTOR'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEDISTRIBUTOR'),
                    'url' => '/admin/distributor'
                ]
            ],
        ];
        $data = Admin::withTrashed()->findOrFail($id);
        return view('admin.admin.distributor-show', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    public function distributor_create(Request $request)
    {
        checkAuthority();
        $page = [
            'page_name' => 'distributor',
            'title' => ts('ADDDISTRIBUTOR'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEDISTRIBUTOR'),
                    'url' => '/admin/distributor'
                ]
            ],
        ];
        $data = new Admin();
        return view('admin.admin.distributor-create', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    public function distributor_store(Request $request)
    {
        checkAuthority();
        $page = [
            'page_name' => 'distributor',
            'title' => ts('DISTRIBUTOR')
        ];

        $unique_id = trim($request->input('unique_id'));
        $password = trim($request->input('password'));
        $ulen = mb_strlen($unique_id);

        $pm = '/^CN[0-9]{4,7}$/';
        $unique_id = str_replace(' ', '', $unique_id);
        $unique_id = strtoupper($unique_id);

        if (!preg_match($pm, $unique_id)) {
            return redirect()-> back()->withInput()->with('trash', ['type'=>'error', 'content'=>'账号不符合规则！账号规则为CN+（4-7）位数字组成！']);
        }
        if ($ulen < 6 || $ulen >9) {
            return redirect()-> back()->withInput()->with('trash', ['type'=>'error', 'content'=>'账号不符合规则！账号规则为CN+（4-7）位数字组成！']);
        }
        if (mb_strlen($password) < 7) {
            return redirect()-> back()->withInput()->with('trash', ['type'=>'error', 'content'=>'密码设置不得低于6位！']);
        }

        $mod = new Admin();
        $mod->unique_id = $unique_id;
        $mod->password = $password;
        if (empty($mod->unique_id) || empty($mod->password)) {
            return redirect()-> back()->withInput()->with('trash', ['type'=>'error', 'content'=>'账号或密码不能为空!']);
        }
        $mod->user_type = 2;
        $mod->abbr = trim($request->input('abbr'));
        $mod->company_name = trim($request->input('company_name'));
        $mod->first_name = trim($request->input('first_name'));
        $mod->email_address = trim($request->input('email_address'));
        $mod->address = trim($request->input('address'));
        $mod->phone_number = trim($request->input('phone_number'));
        $mod->cell_phone = trim($request->input('cell_phone'));
        $mod->wechat_id = trim($request->input('wechat_id'));
        $mod->region_id = (int) $request->input('region_id');
        $mod->province_id = (int) $request->input('province_id');
        $mod->city = (int) $request->input('city');
        $mod->note = trim($request->input('note'));
        $mod->creator_id = (int) $request->input('creator_id');
        $mod->last_name = '';
        $mod->username = '';
        $mod->zip = 0;
        $mod->date_signedup = date('Y-m-d H:i:s');
        $mod->additional_comment = '';
        $mod->province = ts('province_master'.(int) $request->input('province_id'));
        $mod->status = (int) $request->input('status');
        $mod->language_id = (int) $request->input('language_id');
        $extension = trim($request->input('extension'));
        $mod->extension = $extension;

        $mod->created_by = session('admin.id');

        $mod->save();
        return redirect('/'.$request->path().'/')->with('trash', ts('distributorADDSUCCESS'));
    }
    public function distributor_edit(Request $request, $id)
    {
        /*$admin_id = session('admin.id');
        $user_type = session('admin.user_type');
        if ($user_type != 1) {
            if ($user_type != 2) {
                abort(404);
            }
            if ($data->id != $admin_id) {
                abort(404);
            }
        }*/
        checkAuthority();
        $data = Admin::findOrFail($id);
        if ($data->user_type != 2) {
            abort(404);
        }
        $page = [
            'page_name' => 'distributor',
            'title' => ts('DISTRIBUTOR'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEDISTRIBUTOR'),
                    'url' => '/admin/distributor'
                ]
            ],
        ];
        return view('admin.admin.distributor-edit', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    public function distributor_update(Request $request, $id)
    {
        checkAuthority();
        $mod = Admin::findOrFail($id);
        if ($mod->user_type != 2) {
            abort(404);
        }
        $alogs = alogs('省代', '修改', $mod->id, null, $mod);
        // $mod->password = trim($request->input('password'));
        if (empty($mod->unique_id) || empty($mod->password)) {
            return redirect()-> back()->withInput()->with('trash', ['type'=>'error', 'content'=>'账号或密码为空！']);
        }
        if ($mod->user_type != 2) {
            abort(404);
        }
        // $mod->unique_id = strtoupper($mod->unique_id);

        $mod->company_name = trim($request->input('company_name'));
        $mod->abbr = trim($request->input('abbr'));
        $mod->first_name = trim($request->input('first_name'));
        $mod->email_address = trim($request->input('email_address'));
        $mod->address = trim($request->input('address'));
        $mod->phone_number = trim($request->input('phone_number'));
        $mod->cell_phone = trim($request->input('cell_phone'));
        $mod->wechat_id = trim($request->input('wechat_id'));
        $mod->region_id = (int) $request->input('region_id');
        $mod->province_id = (int) $request->input('province_id');
        $mod->city = (int) $request->input('city');
        $mod->note = trim($request->input('note'));
        $mod->creator_id = (int) $request->input('creator_id');
        $mod->last_name = '';
        $mod->username = '';
        $mod->zip = 0;
        $mod->date_signedup = date('Y-m-d H:i:s');
        $mod->additional_comment = '';
        $mod->province = ts('province_master'.(int) $request->input('province_id'));
        $mod->language_id = (int) $request->input('language_id');
        $extension = trim($request->input('extension'));
        $mod->extension = $extension;

        $status = (int) $request->input('status');
        if ($mod->status != $status) {
            $mod->status = $status;
            $mod->save();
            editUserStatus($mod);
        } else {
            $mod->save();
        }
        $mod->save();

        $alogs->new = $mod;
        $alogs->save();
        return redirect('/'.$request->path().'/')->with('trash', ts('DISTRIBUTORUPDATESUCCESS'));
    }
    public function distributor_reset_pwd(Request $request, $id)
    {
        checkAuthority();
        $mod = Admin::findOrFail($id);
        if ($mod->user_type != 2) {
            abort(404);
        }
        $mod->password = 'sR^6&wQf#lABz*M3';
        $mod->save();
        editUserStatus($mod, 3);
        return ['status'=>'SUCCESS', 'msg' => '密码已重置，新密码为：sR^6&wQf#lABz*M3', 'data'=>[
            'msg' => [
                '密码已重置，新密码为',
                'sR^6&wQf#lABz*M3'
            ]
        ]];
    }
    public function distributor_delete(Request $request, $id)
    {
        // abort(404);
        return deleteDistributor($id);
    }
    public function distributor_deletes(Request $request)
    {
        // abort(404);
        $ids = $request->input('id');
        if (empty($ids)) {
            return ['status'=>false, 'msg'=>'没有找到用户！'];
        }
        foreach ($ids as $k => $id) {
            deleteDistributor($id);
        }
        return ['status' => 'SUCCESS', 'msg' => '成功！'];
    }
    public function distributor_edit_status(Request $request)
    {
        checkAuthority();
        $id = $request->input('id');
        $mod = Admin::findOrFail($id);
        $mod->status = (int) (!$mod->status);
        $mod->save();
        editUserStatus($mod);
        session()->flash('hightlight', ['id'=>$id, 'type'=>'distributor']);
        return ['status'=>'SUCCESS'];
    }
    public function distributor_dealer(Request $request, $id)
    {
        checkAuthority();
        $model = Admin::where('creator_id', $id);
        $model = $this->filterByColumns($request, $model, ['id', 'abbr', 'company_name','first_name','phone_number', 'first_name']);
        if ($request->input('status')!==null) {
            $status = (int) $request->input('status');
            if ($status != 1) {
                $status = 0;
            }
            $model = $model->where('status', $status);
        }
        $data = $model->paginate();
        $page = [
            'page_name' => 'distributor',
            'title' => ts('SUBORDINATEDEALER'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEDISTRIBUTOR'),
                    'url' => '/admin/distributor'
                ]
            ],
        ];
        $distributor = Admin::find($id);
        return view('admin.admin.distributor-dealer', [
            'data'=>$data,
            'page' => $page,
            'distributor' => $distributor,
        ]);
    }
    public function distributor_performance(Request $request, $id)
    {
        checkAuthority();
        $page = [
            'page_name' => 'distributor',
            'title' => ts('DISTRIBUTOR') . ts('performance'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEDISTRIBUTOR'),
                    'url' => '/admin/distributor'
                ]
            ],
        ];
        $user = Admin::withTrashed()->findOrFail($id);
        if ($user->user_type != 2) {
            abort(404);
        }
        $model = DB::table('warranty_to_vehicle_coverage as wvc')
            ->leftJoin('warranty as w', 'w.id', 'wvc.warranty_id')
            ->leftJoin('users as u', 'w.user_id', 'u.id')
            ->leftJoin('roll_master as r', 'r.roll_number', 'wvc.roll_number')
            ->leftJoin('film_type_master as fm', 'r.film_type_id', 'fm.id')
            ->whereNull('w.deleted_at')
            ->whereIn('w.user_id', function ($query) use ($user) {
                $query->select('id')->from('users')
                    ->where('id', $user->id)
                    ->orWhere('creator_id', $user->id);
            });
        $model = filterByDateRange($model, 'w.installation_date');
        $model = $this->filterByColumns($request, $model, ['u.abbr', 'wvc.roll_number', 'w.warranty_id', 'w.phone_number']);
        $lan = getLangName();
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
        $data = $model->select(['wvc.roll_number', 'wvc.length', 'u.abbr', 'w.id as wid', 'w.warranty_id', 'r.film_type_id', DB::raw('fm.'.$lan.' as film_type'), 'w.installation_date'])
            ->orderByDesc('w.id')->paginate();

        return view('admin.admin.distributor-performance', [
            'data'=>$data,
            'page' => $page,
            'user' => $user,
            'total' => $total,
            'id' => $id,
        ]);
    }

    /*
     * 省代同步*/
    public function distributor_import(Request $request)
    {
        checkAuthority();
        $page = [
            'page_name' => 'distributor',
            'title' => ts('tongbu'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEDISTRIBUTOR'),
                    'url' => '/admin/distributor'
                ]
            ],
        ];
        return view('admin.distributor.import', [
            'page' => $page,
        ]);
    }


    /*
     * 省代同步操作*/
    public function distributor_handle_import(Request $request)
    {
        checkAuthority();
        $admin_id = session('admin.id');
        $file = $request->file('file');
        if (empty($file)) {
            return back()->withInput()->with('trash', [ 'content' => '文件不能为空！', 'type' => 'error']);
        }
        $ext = $file->getClientOriginalExtension();
        $allow_type = [
            'csv','xls','xlsx'
        ];
        if (!in_array($ext, $allow_type)) {
            return back()->with('trash', [ 'msg' => '不支持的文件类型！', 'type' => 'error']);
        }
        $filename = Str::random(16) . '.xlsx';
        $path = $this->getFileStorePath(2);
        $bool = $file->storeAs($path['path'], $filename, 'storage');
        $full_file_name = $path['path'].$filename;
        $mod = new FileUpload();
        $mod->type = 2;
        $mod->name = '省代同步';
        $mod->user_id = $admin_id;
        $mod->file = $full_file_name;
        $mod->save();
        $pfile = $path['base'] . $filename;
        if (file_exists($pfile)) {
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $row_no = 1;
            $n = 1;
            $data = [];
            while ($n){
                $item = [];
                $n = strtoupper(trim($sheet->getCell('A'.$row_no)->getValue()));
                if ($n) {
                    if ($n != 'ID') {
                        foreach (range('A', 'Q') as $v) {
                            $item[$v] = trim($sheet->getCell($v.$row_no)->getValue());
                        }
                        $data[] = $item;
                    }
                }
                $row_no++;
            }
            $uun = DB::table('users_union')->get();
            $uuids = [];
            foreach ($uun as $v) {
                $uuids[$v->union_id] = $v->user_id;
            }
            $upd = ped1($data); // 将数据转化为省代键数组
            foreach ($upd as $k => $v) {
                $model = new UserSync();
                $model->union_id = $v['id'];
                $model->user_type = 2;
                if (!empty($uuids->$v['id'])) {
                    $model->user_id = $uuids->$v['id'];
                    $cv = ced1($model->user_id, $v); // 检查省代与传入值是否有修改
                    if (!$cv) {
                        $model->status = 0;
                    } else {
                        $model->status = 2;
                    }
                } else {
                    $model->status = 0;
                }
                $model->datas = json_encode($v);
                $model->save();
            }
        }
        return redirect('/admin/user_sync');
    }
    /*
     * 整理EXCEL数据*/



    public function dealer_edit_status(Request $request)
    {
        checkAuthority();
        $id = $request->input('id');
        $mod = Admin::findOrFail($id);
        $mod->status = (int) (!$mod->status);
        $mod->save();
        editUserStatus($mod);
        session()->flash('hightlight', ['id'=>$id, 'type'=>'dealer']);
        return ['status'=>'SUCCESS'];
    }

    public function delete_dealers(Request $request)
    {
        abort(404);
        $ids = $request->input('id');
        if (empty($ids)) {
            return ['status'=>false, 'msg'=>'没有找到用户！'];
        }
        foreach ($ids as $id) {
            $model = Admin::find($id);
            if (!empty($model) && $model->user_type == 3) {
                $model->delete();
            }
        }
        return ['status' => 'SUCCESS', 'msg' => '成功！'];
    }

    public function dealer_index_o(Request $request)
    {
        checkAuthority();
        $page = [
            'page_name' => 'dealer-o',
            'title' => ts('CSjxsGL')
        ];
        /*$model = Admin::where('user_type', 3);*/
        $model = DB::table('users as x')
            ->leftJoin('users as u', 'u.id', 'x.creator_id')
            ->where('x.user_type', 3)
            ->select(['x.*', 'u.abbr as sdqy', 'u.id as sdid'])
            ->where('x.status', '!=', 2);;
        $model = filterByColumns($model, ['x.id', 'x.abbr', 'x.company_name','x.first_name','x.phone_number', 'x.first_name']);
        $status = (int) $request->input('status');
        if ($status == 1) {
            $model = $model->whereNotNull('x.deleted_at');
        } elseif ($status == 2) {
            $model = $model->where('x.status', '!=', 1)->whereNull('x.deleted_at');
        } else {
            $model = $model->where(function ($query) {
                $query->whereNotNull('x.deleted_at')
                    ->orWhere('x.status', '!=', 1);
            });
        }
        $mdtype = $request->input('mdtype');
        if ($mdtype !== null) {
            $model = $model->where('x.mdtype', $mdtype);
        }
        $data = $model->orderByDesc('x.id')->paginate();
        return view('admin.admin.dealer-index-o', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    // 经销商
    public function dealer_index(Request $request)
    {
        $user_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');
        $page = [
            'page_name' => 'dealer',
            'title' => ts('manageDEALERs')
        ];
        /*$model = Admin::where([
            'user_type' => 3
        ]);*/
        $model = DB::table('users as x')
            ->leftJoin('users as u', 'u.id', 'x.creator_id')
            ->where('x.user_type', 3)
            ->whereNull('x.deleted_at')
            ->select(['x.*', 'u.abbr as sdqy', 'u.id as sdid']);
        // 省代
        if ($user_type == 2) {
            $model = $model->where('x.creator_id', $user_id);
        }
        $model = filterByColumns($model, ['x.id', 'x.abbr', 'x.company_name','x.first_name','x.phone_number', 'x.first_name']);
        if ($request->input('status')!==null) {
            $status = (int) $request->input('status');
            if ($status != 1) {
                $status = 0;
            }
            $model = $model->where('x.status', $status);
        }
        $mdtype = $request->input('mdtype');
        if ($mdtype !== null) {
            $model = $model->where('x.mdtype', $mdtype);
        }
        $data = $model->orderByDesc('x.id')->paginate();
        return view('admin.admin.dealer-index', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    public function dealer_show(Request $request, $id)
    {
        $page = [

            'page_name' => 'dealer',
            'title' => ts('DEALER'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEDEALERS'),
                    'url' => '/admin/dealer'
                ]
            ],
        ];
        $data = Admin::withTrashed()->findOrFail($id);
        return view('admin.admin.dealer-show', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    public function dealer_create(Request $request)
    {
        checkAuthority();
        $page = [
            'page_name' => 'dealer',
            'title' => ts('ADDDEALER'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEDEALERS'),
                    'url' => '/admin/dealer'
                ]
            ],
        ];
        $data = new Admin();
        return view('admin.admin.dealer-create', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    public function dealer_store(Request $request)
    {
        checkAuthority();
        $page = [
            'page_name' => 'dealer',
            'title' => ts('DEALER'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEDEALERS'),
                    'url' => '/admin/dealer'
                ]
            ],
        ];
        $mod = new Admin();
        $mod->unique_id = Str::random(64);
        $mod->user_type = 3;
        $mod->company_name = trim($request->input('company_name'));
        $mod->abbr = trim($request->input('abbr'));
        $mod->first_name = trim($request->input('first_name'));
        $mod->email_address = trim($request->input('email_address'));
        $mod->address = trim($request->input('address'));
        $mod->phone_number = trim($request->input('phone_number'));
        $mod->cell_phone = trim($request->input('cell_phone'));
        $mod->wechat_id = trim($request->input('wechat_id'));
        $mod->region_id = (int) $request->input('region_id');
        $mod->province_id = (int) $request->input('province_id');
        $mod->city = (int) $request->input('city');
        $mod->note = trim($request->input('note'));
        $mod->creator_id = (int) $request->input('creator_id');
        $mod->last_name = '';
        $mod->username = '';
        $mod->zip = 0;
        $mod->date_signedup = date('Y-m-d H:i:s');
        $mod->additional_comment = '';
        $mod->province = ts('province_master'.(int) $request->input('province_id'));
        $mod->status = (int) $request->input('status');
        $mod->language_id = (int) $request->input('language_id');
        $extension = trim($request->input('extension'));
        $mod->extension = $extension;

        $mod->created_by = session('admin.id');

        $mod->password = Str::random(64);

        $mod->company_name = $mod->company_name ?: $mod->abbr;
        $mod->mdtype = (int) $request->input('mdtype');

        $mod->save();
        $alogs = alogs('经销商', '添加', $mod->id, $mod, null);
        return redirect('/'.$request->path().'/')->with('trash', ts('DEALERADDSUCCESS'));
    }
    public function dealer_edit(Request $request, $id)
    {
        $page = [
            'page_name' => 'dealer',
            'title' => ts('editDEALER'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEDEALERS'),
                    'url' => '/admin/dealer'
                ]
            ],
        ];
        $data = Admin::findOrFail($id);
        if ($data->user_type != 3) {
            abort(404);
        }
        return view('admin.admin.dealer-edit', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    public function dealer_update(Request $request, $id)
    {
        $page = [
            'page_name' => 'dealer',
            'title' => ts('DEALER')
        ];
        $mod = Admin::findOrFail($id);
        if ($mod->user_type != 3) {
            abort(404);
        }
        $alogs = alogs('经销商', '修改', $mod->id, null, $mod);
        $mod->company_name = trim($request->input('company_name'));
        $mod->abbr = trim($request->input('abbr'));
        $mod->first_name = trim($request->input('first_name'));
        $mod->email_address = trim($request->input('email_address'));
        $mod->address = trim($request->input('address'));
        $mod->phone_number = trim($request->input('phone_number'));
        $mod->cell_phone = trim($request->input('cell_phone'));
        $mod->wechat_id = trim($request->input('wechat_id'));
        $mod->region_id = (int) $request->input('region_id');
        $mod->province_id = (int) $request->input('province_id');
        $mod->city = (int) $request->input('city');
        $mod->note = trim($request->input('note'));
        $mod->creator_id = (int) $request->input('creator_id');

        $extension = trim($request->input('extension'));
        $mod->extension = $extension;

        $mod->updated_by = session('admin.id');

        $mod->company_name = $mod->company_name ?: $mod->abbr;
        $mod->mdtype = (int) $request->input('mdtype');

        $status = (int) $request->input('status');
        if ($mod->status != $status) {
            $mod->status = $status;
            $mod->save();
            editUserStatus($mod);
        } else {
            $mod->save();
        }
        $alogs->new = $mod;
        $alogs->save();

        # return redirect('/admin')->with('trash', ['content' => ts('DEALERUPDATESUCCESS'), 'type' => 'error']);
        return redirect('/'.$request->path().'/')->with('trash', ts('DEALERUPDATESUCCESS'));
    }
    public function dealer_delete(Request $request, $id)
    {
        // abort(404);
        return deleteDealer($id);
        abort(404);
        $page = [
            'page_name' => 'dealer',
            'title' => ts('DEALER')
        ];
        $data = Admin::findOrFail($id);
        return view('admin.users.dealer_delete', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    public function dealer_deletes(Request $request)
    {
        // abort(404);
        $ids = $request->input('id');
        if (empty($ids)) {
            return ['status'=>false, 'msg'=>'没有找到用户！'];
        }
        foreach ($ids as $k => $id) {
            deleteDealer($id);
        }
        return ['status' => 'SUCCESS', 'msg' => '成功！'];
    }
    public function dealer_performance(Request $request, $id)
    {
        $page = [
            'page_name' => 'dealer',
            'title' => ts('DEALERPERFORMANCE'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEDEALERS'),
                    'url' => '/admin/dealer'
                ]
            ],
        ];
        $user = Admin::withTrashed()->findOrFail($id);
        $users = [$id];
        if ($user->user_type != 3) {
            return abort(404);
        }
        $model = DB::table('warranty_to_vehicle_coverage as wvc')
            ->leftJoin('warranty as w', 'w.id', 'wvc.warranty_id')
            ->leftJoin('roll_master as r', 'r.roll_number', 'wvc.roll_number')
            ->whereNull('w.deleted_at')
            ->where('w.user_id', $user->id);
        $model = filterByDateRange($model, 'w.installation_date');
        $model = $this->filterByColumns($request, $model, ['wvc.roll_number', 'w.warranty_id', 'w.phone_number']);
        $total = $model->sum('wvc.length');
        $data = $model->select(['wvc.roll_number', 'wvc.length', 'w.warranty_id', 'r.film_type_id', 'w.installation_date'])
            ->orderByDesc('w.id')->paginate();

        return view('admin.admin.dealer-performance', [
            'data'=>$data,
            'page' => $page,
            'user' => $user,
            'total' => $total,
        ]);
    }
    // 车主管理
    public function customer_index(Request $request)
    {
        $user_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');
        $page = [
            'page_name' => 'customer',
            'title' => ts('MANAGECAROWNERS')
        ];
        $model = DB::table('warranty as w')
            ->leftJoin('users as u1', 'w.user_id', 'u1.id')
            ->leftJoin('users as u2', 'u1.creator_id', 'u2.id')
            ->whereNull('w.deleted_at')
            ->whereNotNull('w.first_name')
            ->where('w.approved', 1);
        if ($user_type == 2) {
            $model = $model->whereIn('w.user_id', function ($query) use ($user_id) {
                $query->select('id')->from('users')
                    ->where('users.id', $user_id)
                    ->orWhere('creator_id', $user_id);
            });
        }
        $model = $this->filterByColumns($request, $model, ['w.warranty_id', 'w.first_name','w.phone_number','w.license_plate']);
        $data = $model->select(['w.id', 'w.warranty_id', 'w.first_name','w.license_plate', 'u1.abbr as dealer_name', 'u1.user_type as dealer_type', 'u2.abbr as distributor_name'])
            ->orderByDesc('w.id')->paginate();
        return view('admin.admin.customer-index', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    public function customer_show(Request $request, $id)
    {
        $page = [
            'page_name' => 'customer',
            'title' => ts('VIEWCAROWNERS'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGECAROWNERS'),
                    'url' => '/admin/customer'
                ]
            ],
        ];
        $data = Warranty::findOrFail($id);
        return view('admin.admin.customer-show', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    // 根据产品roll_number批量更新产品剩余长度
    // 传入的是collection
    protected function urolls($datas) {
        $rns = Arr::pluck($datas, 'roll_number');
        $rm = []; // 主表，兼容旧数据
        foreach ($datas as $k => $v) {
            $rm[$v->roll_number] = $v->length;
        }
        // 获取质保使用数据
        $rw = DB::table('warranty_to_vehicle_coverage as wvc')
            ->whereIn('roll_number', $rns)
            ->leftJoin('warranty as w', 'w.id', 'wvc.warranty_id')
            ->get(['roll_number', 'length', 'user_id']);// 获取质保使用数据
        $pw = DB::table('precut as p')
            ->leftJoin('precut_to_vehicle_coverage as pvc', 'pvc.precut_kit_sale_id', 'p.id')
            ->whereIn('roll_number', $rns)
            ->where('p.status', '!=', 1)
            ->select('pvc.roll_number', 'pvc.length', 'p.created_by')
            ->get();
        $used_len = [];
        $utlen = []; // 总用量
        foreach ($rw as $k => $v) {
            $key = $v->roll_number.'###'.$v->user_id;
            $used_len[$key] = @$used_len[$key] + $v->length;

            $rm[$v->roll_number] = $rm[$v->roll_number] - $v->length; // 主表数据总长度减去使用长度
        }
        foreach ($pw as $k => $v) {
            $key = $v->roll_number.'###'.$v->created_by;
            $used_len[$key] = @$used_len[$key] + $v->length;

            $rm[$v->roll_number] = $rm[$v->roll_number] - $v->length; // 主表数据总长度减去使用长度
        }
        // 设置更新日期，设置日期，假如近期没有更新过，则更新数据
        // 同样，单独更新时，也检查数据
        $date = '2020-10-01';
        foreach ($used_len as $k => $v) {
            $e = explode('###', $k);
            $d = RollRestlen::where([
                'roll_number' => $e[0],
                'user_id' => $e[1],
            ])->first();
            if (empty($d) || empty($d->id)) {
                $d = new RollRestlen();
                $d->roll_number = $e[0];
                $d->user_id = $e[1];
                $d->restlen = 0; // 0 - $v，不计负数
                $d->save();
            } else {
                if ($d->updated_at < $date) {
                    $rl1 = @$d->restlen - $v;
                    $rl2 = $rm[$d->roll_number] > 0 ? $rm[$d->roll_number] : 0;
                    if ($rl1 >= $rl2) {
                        $d->restlen = $rl2;
                    } else {
                        $d->restlen = $rl1;
                    }
                    $d->save();
                }
            }
        }

        foreach ($rm as $k => $v) {
            $rl2 = $v > 0 ? $v : 0;
            $this->uproll($k, $rl2);
        }
    }
    // 根据产品roll_number更新产品剩余长度，单更新
    protected function uroll($roll) {
        $rn = $roll->roll_number;
        $rm = $roll->length; // 长度
        // 获取质保使用数据
        $rw = DB::table('warranty_to_vehicle_coverage as wvc')
            ->where('roll_number', $rn)
            ->leftJoin('warranty as w', 'w.id', 'wvc.warranty_id')
            ->get(['roll_number', 'length', 'user_id']);// 获取质保使用数据
        $pw = DB::table('precut as p')
            ->leftJoin('precut_to_vehicle_coverage as pvc', 'pvc.precut_kit_sale_id', 'p.id')
            ->where('roll_number', $rn)
            ->where('p.status', '!=', 1)
            ->select('pvc.roll_number', 'pvc.length', 'p.created_by')
            ->get();
        $used_len = [];
        foreach ($rw as $k => $v) {
            $key = $v->roll_number.'###'.$v->user_id;
            $used_len[$key] = @$used_len[$key] + $v->length;
            $rm -= $v->length;
        }
        foreach ($pw as $k => $v) {
            $key = $v->roll_number.'###'.$v->created_by;
            $used_len[$key] = @$used_len[$key] + $v->length;
            $rm -= $v->length;
        }
        // 设置更新日期，设置日期，假如近期没有更新过，则更新数据
        // 同样，单独更新时，也检查数据
        $date = '2020-10-01';
        foreach ($used_len as $k => $v) {
            $e = explode('###', $k);
            $d = RollRestlen::where([
                'roll_number' => $e[0],
                'user_id' => $e[1],
            ])->first();
            if (empty($d) || empty($d->id)) {
                $d = new RollRestlen();
                $d->roll_number = $e[0];
                $d->user_id = $e[1];
                $d->restlen = 0; // 0 - $v，不计负数
                $d->save();
            } else {
                if ($d->updated_at < $date) {
                    $rl1 = @$d->restlen - $v;
                    $rl2 = $rm > 0 ? $rm : 0;
                    if ($rl1 >= $rl2) {
                        $d->restlen = $rl2;
                    } else {
                        $d->restlen = $rl1;
                    }
                    $d->save();
                }
            }
        }
        $rl2 = $rm > 0 ? $rm : 0;
        $this->uproll($rn, $rl2);
    }
    // 根据产品roll_number更新产品剩余长度，单更新
    protected function uroll2($roll) {
        $rn = $roll->roll_number;
        $rm = $roll->length; // 长度
        // 获取质保使用数据
        $rw = DB::table('warranty_to_vehicle_coverage as wvc')
            ->where('roll_number', $rn)
            ->leftJoin('warranty as w', 'w.id', 'wvc.warranty_id')
            ->get(['roll_number', 'length', 'user_id']);// 获取质保使用数据
        $pw = DB::table('precut as p')
            ->leftJoin('precut_to_vehicle_coverage as pvc', 'pvc.precut_kit_sale_id', 'p.id')
            ->where('roll_number', $rn)
            ->where('p.status', '!=', 1)
            ->select('pvc.roll_number', 'pvc.length', 'p.created_by')
            ->get();
        foreach ($rw as $k => $v) {
            $rm -= $v->length;
        }
        foreach ($pw as $k => $v) {
            $rm -= $v->length;
        }
        $rl2 = $rm > 0 ? $rm : 0;
        $this->uproll($rn, $rl2);
    }
    // 批量更新长度
    protected function uproll($roll_number, $len)
    {
        RollRestlen::where('roll_number', $roll_number)
            ->where('restlen', '>', $len)
            ->update(['restlen'=>$len]);
    }
    // 产品管理-膜卷管理
    public function rolls_index(Request $request)
    {
        $user_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');
        $page = [
            'page_name' => 'rolls',
            'title' => ts('MANAGEROLL'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEPRODUCT'),
                    'url' => 'javascript:void(0)'
                ]
            ],
        ];
        $model = DB::table('roll_master')->whereNull('roll_master.deleted_at');
        if ($user_type == 2) {
            $model = $model->whereIn('id', function ($query) use ($user_id) {
                $query->select('roll_sales.roll_id')->from('roll_sales')
                    ->leftJoin('users', 'roll_sales.sold_to_user_id', '=', 'users.id')
                    ->where('users.id', $user_id)
                    ->orWhere('users.creator_id', $user_id)
                    ->groupBy('roll_sales.roll_id');
            });
        }

        $startdate = trim($request->input('startdate'));
        $enddate = trim($request->input('enddate'));
        if ($startdate) {
            $model = $model->where('created_at', '>=', $startdate);
        }
        if ($enddate) {
            $model = $model->where('created_at', '<=', $enddate);
        }

        $allocated = (int) $request->input('allocated');
        $raw_DB = DB::raw('(select roll_sales.roll_id, roll_sales.id as roll_sales_id from roll_sales GROUP by roll_id order by id desc) as roll_sales');
        $model = $model->leftJoin($raw_DB, 'roll_master.id', '=', 'roll_sales.roll_id');
        if ($allocated == 1) {
            $model->whereNotNull('roll_sales.roll_id');
        } elseif ($allocated == 2) {
            $model->whereNull('roll_sales.roll_id');
        }
        $model = $this->filterRolls($request, $model);
        $p1 = $model->orderByDesc('id')->paginate();
        $ids = Arr::pluck($p1, 'id');
        $rns = Arr::pluck($p1, 'roll_number');
        // $this->urolls($p1); // 批量更新，持续到某一段时间后不再继续

        $roll_arr = DB::table('roll_restlen')->whereIn('roll_number', $rns)->get();
        $rolls = []; // 尚未分配到货物的长度
        $freelen = []; // 空闲可分配长度
        $restlen = []; // 剩余尚未出售的总长度
        foreach ($roll_arr as $v) {
            if (empty($rolls[$v->roll_number])) {
                $rolls[$v->roll_number] = $v;
            } else {
                $rolls[$v->roll_number]->restlen += $v->restlen;
            }
            $restlen[$v->roll_number] = @$restlen[$v->roll_number] + $v->restlen;
            if ($user_type == 1 && $v->user_id ==1) {
                $freelen[$v->roll_number] = @$freelen[$v->roll_number] + $v->restlen;
            }else if ($v->user_id == $user_id) {
                $freelen[$v->roll_number] = @$freelen[$v->roll_number] + $v->restlen;
            }
        }
        foreach ($rns as $v) {
            if (empty($freelen[$v])) {
                $freelen[$v] = 0;
            }
            if (empty($restlen[$v])) {
                $restlen[$v] = 0;
            }
        }
        return view('admin.admin.rolls-index-admin', [
            'data'=>$p1,
            'page' => $page,
            'rolls' => $rolls,
            'freelen' => $freelen,
            'restlen' => $restlen,
        ]);
    }
    protected function filterRolls(Request $request, $model)
    {
        $value = $request->input('value');
        if (empty($value)) {
            return $model;
        }
        $film = DB::table('film_type_master');
        $film = $this->filterByColumns($request, $film, ['english_value', 'traditional_chiness_value', 'simplified_chiness_value']);
        $film_ids = $film->get('id');
        $model = $model->where(function ($query) use ($value, $film_ids) {
            $query->orWhereIn('film_type_id', Arr::pluck($film_ids, 'id'))
                ->orWhere('roll_number', 'like', '%'.$value.'%');
        });
        return $model;
    }
    public function rolls_show(Request $request, $id)
    {
        $user_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');
        $page = [
            'page_name' => 'rolls',
            'title' => ts('VIEWROLLS'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEROLL'),
                    'url' => '/admin/rolls'
                ]
            ],
        ];
        $data = RollMaster::withTrashed()->findOrFail($id);
        $this->uroll($data);
        $w = DB::table('warranty as w')
            ->leftJoin('warranty_to_vehicle_coverage as wvc', 'w.id', '=', 'wvc.warranty_id')
            ->leftJoin('users', 'w.user_id', '=', 'users.id')
            ->leftJoin('users as c', 'users.creator_id', '=', 'c.id')
            ->where('wvc.roll_number', $data->roll_number)
            ->leftJoin('precut as p', 'w.pre_id', 'p.id')
            ->whereNull('w.deleted_at')
            ->orderByDesc('w.id')
            ->select(['wvc.roll_number', 'wvc.length', 'p.id as pid', 'p.precut_id as p_id', 'w.id as wid', 'w.warranty_id as w_id', 'w.approved_date as effective_time', 'users.abbr as dealer_name', 'users.user_type', 'c.abbr as distributor_name', DB::raw('1 as rtype')]);
        $p = DB::table('precut as p')
            ->leftJoin('precut_to_vehicle_coverage as pvc', 'pvc.precut_kit_sale_id', '=', 'p.id')
            ->where('roll_number', $data->roll_number)
            ->whereNull('p.deleted_at')
            ->where('p.status', '!=', 1)
            ->leftJoin('users', 'p.user_id', '=', 'users.id')
            ->leftJoin('users as c', 'users.creator_id', '=', 'c.id')
            ->select(['pvc.roll_number', 'pvc.length', 'p.id as pid', 'p.precut_id as p_id', DB::raw('null as wid'), DB::raw('null as w_id'), 'p.created_at as effective_time', 'users.abbr as dealer_name', 'users.user_type', 'c.abbr as distributor_name', DB::raw('2 as rtype')]);
        if ($user_type != 1) {
            $w = $w->whereIn('w.user_id', function ($query) use ($user_id) {
                $query->select('id')->from('users')
                    ->where('id', $user_id)
                    ->orWhere('creator_id', $user_id);
            });
            $p = $p->whereIn('p.user_id', function ($query) use ($user_id) {
                $query->select('id')->from('users')
                    ->where('id', $user_id)
                    ->orWhere('creator_id', $user_id);
            });
        }
        $m = $w->union($p)->get();
        $rs = DB::table('roll_sales as rs')
            ->leftJoin('users as x', 'x.id', 'rs.sold_to_user_id')
            ->leftJoin('users as y', 'y.id', 'x.creator_id')
            ->leftJoin('users as z', 'z.id', 'rs.sold_by_user_id')
            ->where('rs.type', 1)
            ->where('rs.roll_id', $id)
            ->orderByDesc('rs.id');
        $roll_sales = $rs->get(['rs.*', DB::raw('if(x.user_type=3, x.abbr, "-") as jxsmc'), 'x.user_type', DB::raw('if(x.user_type=3, y.abbr, x.abbr) as sdmc'), DB::raw('z.abbr as bfpr')]);
        // return [$rs->toSql()];
        return view('admin.admin.rolls-show', [
            'data'=>$data,
            'page' => $page,
            'wRecords' => $m,
            'assigns' => $roll_sales,
            // 'w' => [$w, $p, $u1]
        ]);
    }
    public function rolls_create(Request $request)
    {
        checkAuthority(1);
        $page = [
            'page_name' => 'rolls',
            'title' => ts('addROLL'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEROLL'),
                    'url' => '/admin/rolls'
                ]
            ],
        ];
        $data = new RollMaster();
        return view('admin.admin.rolls-create', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    public function rolls_store(Request $request)
    {
        checkAuthority(1);
        $url = $request->input('_previous_') ?: '/admin/rolls';
        $roll_number = trim($request->input('roll_number'));
        $data = RollMaster::where('roll_number', $roll_number)->get();
        if (count($data)) {
            return redirect($url)->with('trash', ['content'=>'卷号已存在！', 'type'=>'error']);
        }
        $model = new RollMaster();
        $model->roll_number = $roll_number;
        $model->width = (int) $request->input('width');
        $model->film_type_id = (int) $request->input('film_type_id');
        $model->length = (int) $request->input('length');
        $model->save();
        $alogs = alogs('膜卷', '添加', $model->id, $model, null);
        plusRestlen(1, $model->roll_number, $model->length);

        $url = $request->input('_previous_') ?: '/admin/rolls';
        $url = '/admin/rolls';
        session()->flash('hightlight', ['id'=>$model->id, 'type'=>'rolls']);
        return redirect($url)->with('trash', ts('ROLLADDSUCCESS'));
    }

    public function rolls_edit(Request $request, $id)
    {
        checkAuthority(1);
        $page = [
            'page_name' => 'rolls',
            'title' => ts('editROLL'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEROLL'),
                    'url' => '/admin/rolls'
                ]
            ],
        ];
        // withTrashed()->
        $data = RollMaster::findOrFail($id);
        return view('admin.admin.rolls-edit', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    /*
     * 膜卷更新
     * 已删除的卷，无法修改
     * 已分配长度的卷，无法修改长度
     * 当管理员的剩余长度与卷长度不符合时，不允许修改长度
     * 长度修改后，自动更新管理员剩余长度*/
    public function rolls_update(Request $request, $id)
    {
        checkAuthority(1);
        $url = '/admin/rolls'; // $request->input('_previous_') ?:
        $model = RollMaster::findOrFail($id);

        $alogs = alogs('膜卷', '修改', $model->id, null, $model);
        $model->width = (int) $request->input('width');
        $model->film_type_id = (int) $request->input('film_type_id');
        $length = (int) $request->input('length');

        $rlen = RollRestlen::firstOrNew([
            'user_id'=>1,
            'roll_number' => $model->roll_number
        ]);
        if ($model->length == $rlen->restlen) {
            $model->length = $length;
        }
        $rlen->restlen = $model->length;
        $rlen->save();
        $model->save();
        $alogs->new = $model;
        $alogs->save();

        session()->flash('hightlight', ['id'=>$model->id, 'type'=>'rolls']);
        return redirect($url)->with('trash', ts('UPDATESUCCESS'));
    }

    /*ajax操作*/
    public function rolls_delete(Request $request, $id)
    {
        return deleteRoll($id);
    }
    /*ajax操作*/
    public function rolls_deletes(Request $request)
    {
        $ids = $request->input('id');
        if (empty($ids)) {
            return ['status'=>false, 'msg'=>'没有找到卷号'];
        }
        foreach ($ids as $k => $id) {
            deleteRoll($id);
        }
        return ['status' => 'SUCCESS', 'msg' => '成功！'];
    }

    /*
     * 卷膜分配*/
    public function rolls_assign(Request $request)
    {
        $admin_id = session('admin.id');
        $user_type = session('admin.user_type');
        $sold_by_user_id = session('admin.id');
        $roll_number = (int) $request->input('roll_id');
        $sold_to_user_id = (int) $request->input('user_id');
        $length = (int) $request->input('length');
        $all_length = (int) $request->input('all_length');
        $model = RollMaster::where('roll_number', $roll_number)->first();
        if (empty($model->id)) {
            return ['status' => false, 'msg' => '没有找到卷号！'];
        }
        $rmodel = RollRestlen::where([
            'roll_number' => $roll_number,
            'user_id' => $sold_by_user_id,
        ])->first();
        if (empty($rmodel) || empty($rmodel->id)) {
            return ['status' => false, 'msg' => '缺少权限！'];
        }
        $residue_length = $rmodel->restlen;
        if ($all_length == 1) {
            $length = $residue_length;
        }
        if ($length >= $residue_length) {
            $length = $residue_length;
        }
        addRollSales($model->id, $sold_by_user_id, $sold_to_user_id, $length, 1);
        minusRestlen($sold_by_user_id, $roll_number, $length);
        plusRestlen($sold_to_user_id, $roll_number, $length);
        session()->flash('hightlight', ['id'=>$model->id, 'type'=>'rolls']);
        return ['status' => 'SUCCESS', 'msg' => '成功！'];
    }

    public function rolls_transfer_index(Request $request)
    {
        $user_id = session('admin.id');
        $user_type = session('admin.user_type');
        $page = [
            'page_name' => 'rolls',
            'title' => ts('DHGL'),
            /*'breadcrumb' => [
                [
                    'text' => ts('MANAGEPRODUCT'),
                    'url' => 'javascript:void(0)'
                ]
            ],*/
        ];
        $model = DB::table('roll_transfer as rt')
            ->leftJoin('roll_master as rm', 'rm.id', 'rt.roll_id')
            ->leftJoin('users as y', 'y.id', 'rt.transfer_by_user_id')
            ->leftJoin('users as x', 'x.id', 'rt.transfer_to_user_id')
            ->leftJoin('users as c', 'c.id', 'rt.created_by')
            ->select(['rt.*', DB::raw('y.abbr as ygmsmc'), DB::raw('x.abbr as xgmsmc'), 'c.abbr as czy', 'rm.roll_number', 'rm.film_type_id']);
        $model = $this->filterByColumns($request, $model, ['rm.roll_number', 'y.abbr', 'x.abbr', 'c.abbr' ]);
        $model = filterByDateRange($model, 'rt.created_at');
        if ($request->input('status')!==null) {
            $status = (int) $request->input('status');
            $model = $model->where('rt.approved', $status);
        }
        /*
         * 如果用户为管理员 */
        if ($user_type == 1) {
            $model = $model->where('c.id', 1);
        } else {
            $model = $model->where(function ($query) use ($user_id) {
                $query->where('y.id', $user_id)
                    ->orWhere('y.creator_id', $user_id)
                    ->orWhere('x.id', $user_id)
                    ->orWhere('x.creator_id', $user_id);
                    /*->orWhere('c.id', $user_id)
                    ->orWhere('c.creator_id', $user_id);*/
            });
        }
        $model = $model->orderByDesc('rt.id');
        $data = $model->paginate();
        // return [$user_type];
        // rolls-transfer-index
        return view('admin.admin.rolls-transfer-index', [
            'data'=>$data,
            'page' => $page,
        ]);
    }

    public function rolls_transfer_store(Request $request)
    {
        $roll_number = $request->input('roll_number');
        $transfer_by_user_id = (int) $request->input('ygmsid');
        $transfer_to_user_id = (int) $request->input('xgmsid');

        $xsdid = (int) $request->input('xsdid');

        $transfer_to_user_id = $transfer_to_user_id ?: $xsdid;

        $length = (int) $request->input('length');
        $note = $request->input('note');
        $approved = 0;
        $created_by = session('admin.id');
        if ($length <= 0) {
            return back()->withInput()->with('trash', ['content'=>'调货长度不能为空', 'type'=>'error']);
        }
        if (empty($transfer_by_user_id)) {
            return back()->withInput()->with('trash', ['content'=>'原购买商不能为空', 'type'=>'error']);
        }
        if (empty($transfer_to_user_id)) {
            return back()->withInput()->with('trash', ['content'=>'现购买商不能为空', 'type'=>'error']);
        }

        $roll = getRollsMasterByRollNumber($roll_number);
        $rmodel = RollRestlen::firstOrNew([
            'user_id' => $transfer_by_user_id,
            'roll_number' => $roll_number,
        ]);
        $rlength = $rmodel->restlen;
        if ($length > $rlength) {
            return back()->withInput()->with('trash', ['content'=>'允许最大调货长度为：'.$rlength.'cm', 'type'=>'error']);
        }
        addRollTransfer($roll->id, $transfer_by_user_id, $transfer_to_user_id, $length, $note);
        minusRestlen($transfer_by_user_id, $roll_number, $length);

        $url = $request->input('_previous_') ?: '/admin/rolls';
        $url = '/admin/rolls';
        return redirect($url)->with('trash', ts('FQDHSUCCESS'));
    }


    public function rolls_transfer_create(Request $request)
    {
        $user_id = session('admin.id');
        $user_type = session('admin.user_type');
        // checkAuthority(1);
        $page = [
            'page_name' => 'rolls',
            'title' => ts('FAQIDIAOHUO'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEPRODUCT'),
                    'url' => 'javascript:void(0)'/*
                    'text' => ts('dhjl'),
                    'url' => '/admin/rolls-transfer'*/
                ]
            ],
        ];
        $data = new RollMaster();
        return view('admin.admin.rolls-transfer-create', [
            'data'=>$data,
            'page' => $page,
        ]);
    }

    public function verify_transfer_index(Request $request)
    {
        checkAuthority(1);
        $page = [
            'page_name' => 'verify_transfer',
            'title' => ts('VERIFYTRANSFER'),
        ];
        $model = DB::table('roll_transfer as rt')
            ->leftJoin('roll_master as rm', 'rm.id', 'rt.roll_id')
            ->leftJoin('users as y', 'y.id', 'rt.transfer_by_user_id')
            ->leftJoin('users as x', 'x.id', 'rt.transfer_to_user_id')
            ->leftJoin('users as c', 'c.id', 'rt.created_by')
            ->select(['rt.*', DB::raw('y.abbr as ygmsmc'), DB::raw('x.abbr as xgmsmc'), 'c.abbr as czy', 'rm.roll_number', 'rm.film_type_id']);
        $model = $this->filterByColumns($request, $model, ['rm.roll_number', 'y.abbr', 'x.abbr', 'c.abbr' ]);
        $model = filterByDateRange($model, 'rt.created_at');
        if ($request->input('status')!==null) {
            $status = (int) $request->input('status');
            $model = $model->where('rt.approved', $status);
        }
        $model = $model->orderByDesc('rt.id');
        $data = $model->paginate();
        // rolls-transfer-index
        return view('admin.admin.verify-transfer-index', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    public function verify_transfer_show(Request $request, $id)
    {
        checkAuthority(1);
        $page = [
            'page_name' => 'rolls',
            'title' => ts('dhshxq'),
            'breadcrumb' => [
                [
                    'text' => ts('VERIFYTRANSFER'),
                    'url' => '/admin/verify/transfer/'
                ]
            ],
        ];
        $data = RollTransfer::findOrFail($id);

        $model = DB::table('roll_transfer as rt')
            ->leftJoin('roll_master as rm', 'rm.id', 'rt.roll_id')
            ->leftJoin('users as y', 'y.id', 'rt.transfer_by_user_id')
            ->leftJoin('users as x', 'x.id', 'rt.transfer_to_user_id')
            ->leftJoin('users as c', 'c.id', 'rt.created_by')
            ->leftJoin('users as ys', 'ys.id', 'y.creator_id')
            ->leftJoin('users as xs', 'xs.id', 'x.creator_id')
            ->select(['rt.*','rm.roll_number', 'rm.film_type_id', 'rm.length as tlength',
                'y.abbr as ygmsmc', 'y.user_type as ygms_ut', 'ys.abbr as ysd',
                'x.abbr as xgmsmc', 'x.user_type as xgms_ut', 'xs.abbr as xsd',
                'c.abbr as czy',
                ])
            ->where('rt.id', $id)
            ->first();

        $restlen = DB::table('roll_restlen')
            ->where('roll_number', $model->roll_number)
            ->sum('restlen');


        return view('admin.admin.verify-transfer-show', [
            'data'=>$model,
            'page' => $page,
            'restlen' => $restlen,
        ]);
    }

    public function verify_transfer_records(Request $request, $id)
    {
        checkAuthority(1);
        $page = [
            'page_name' => 'rolls',
            'title' => ts('dhjl'),
            'breadcrumb' => [
                [
                    'text' => ts('VERIFYTRANSFER'),
                    'url' => '/admin/verify/transfer/'
                ]
            ],
        ];
        $rtmodel = RollTransfer::findOrFail($id);
        // DB::table()
        $model = DB::table('roll_transfer as rt')
            ->leftJoin('roll_master as rm', 'rm.id', 'rt.roll_id')
            ->leftJoin('users as y', 'y.id', 'rt.transfer_by_user_id')
            ->leftJoin('users as x', 'x.id', 'rt.transfer_to_user_id')
            ->leftJoin('users as c', 'c.id', 'rt.created_by')
            ->where('rm.id', $rtmodel->roll_id)
            ->select(['rt.*', DB::raw('y.abbr as ygmsmc'), DB::raw('x.abbr as xgmsmc'), 'c.abbr as czy', 'rm.roll_number', 'rm.film_type_id'])
            ->orderByDesc('rt.id')
            ->get();

        $restlen = DB::table('roll_restlen')
            ->where('roll_number', $rtmodel->roll_number)
            ->sum('restlen');
        $roll = DB::table('roll_master')->find($rtmodel->roll_id);


        return view('admin.admin.verify-transfer-records', [
            'roll'=> $roll,
            'page' => $page,
            'details' => $model,
            'restlen' => $restlen,
        ]);
    }

    public function verify_transfer_verify(Request $request, $id)
    {
        checkAuthority(1);
        $verify = (int) $request->input('verify');
        if (!in_array($verify, [1, 2])) {
            return ['status'=>false, 'msg'=>'参数错误'];
        }
        $data = RollTransfer::findOrFail($id);
        if ($data->approved != 0) {
            return ['status'=>false, 'msg'=>'信息状态错误，请刷新重试，如多次出现请联系管理员！'];
        }
        $data->approved = $verify;
        $data->approved_by_user_id = session('admin.id');
        $data->approved_date = date('Y-m-d H:i:s');
        $data->save();
        $alogs = alogs('调货', '审核', $data->id, $data, null);

        if ($verify == 1) {
            addRollSales($data->roll_id, $data->transfer_by_user_id, $data->transfer_to_user_id, $data->length, 2);
            plusRestlen($data->transfer_to_user_id, getRollNo($data->roll_id), $data->length);
        } else {
            plusRestlen($data->transfer_by_user_id, getRollNo($data->roll_id), $data->length);
        }
        return ['status' => 'SUCCESS', 'msg' => '成功！'];
    }

    public function verify_warranty_index(Request $request)
    {
        checkAuthority(1);
        $page = [
            'page_name' => 'verify_warranty',
            'title' => ts('VERIFYwarranty'),
        ];
        $model = DB::table('warranty_verify as wv')
            ->leftJoin('warranty as w', 'w.id', 'wv.warranty_id')
            ->leftJoin('users as y', 'y.id', 'wv.created_by')
            ->leftJoin('users as x', 'x.id', 'w.user_id')
            ->select(['wv.*', 'y.abbr as sqr', 'x.abbr as jxsmc', 'w.installer_name', 'w.license_plate']);
        $model = $this->filterByColumns($request, $model, ['y.abbr', 'x.abbr', 'w.installer_name', 'w.license_plate', 'w.warranty_id' ]);
        $model = filterByDateRange($model, 'wv.created_at');
        if ($request->input('status')!==null) {
            $status = (int) $request->input('status');
            $model = $model->where('wv.approved', $status);
        }
        $model = $model->orderByDesc('id');
        $data = $model->paginate();
        $ids = Arr::pluck($data, 'warranty_id');
        $c = DB::table('warranty_to_vehicle_coverage')->whereIn('warranty_id', $ids)
            ->get(['warranty_id', 'roll_number', 'length']);
        $coverages = [];
        foreach ($c as $k => $v) {
            if (empty($coverages[$v->warranty_id])) {
                $coverages[$v->warranty_id] = [];
            }
            $coverages[$v->warranty_id][] = $v;
        }
        return view('admin.admin.verify-warranty-index', [
            'data'=>$data,
            'page' => $page,
            'coverages' => $coverages,
        ]);
    }
    public function verify_warranty_show(Request $request, $id)
    {
        checkAuthority(1);
        $page = [
            'page_name' => 'verify_warranty',
            'title' => ts('ZBSHXQ'),
            'breadcrumb' => [
                [
                    'text' => ts('VERIFYwarranty'),
                    'url' => '/admin/verify/warranty/'
                ]
            ],
        ];
        $wv = WarrantyVerify::findOrFail($id);
        $w = Warranty::findOrFail($wv->warranty_id);

        $r = DB::table('warranty_verify as w')
            ->leftJoin('users as x', 'x.id', 'w.created_by')
            ->leftJoin('users as y', 'y.id', 'w.updated_by')
            ->where('w.id', '!=', $wv->id)
            ->where('w.warranty_id', $w->id)
            ->get(['w.*', 'x.abbr as fqr', 'y.abbr as czy']);

        return view('admin.admin.verify-warranty-show', [
            'data'=>$w,
            'page' => $page,
            'verify' => $wv,
            'records' => $r,
        ]);
    }

    public function verify_warranty_verify(Request $request, $id)
    {
        checkAuthority(1);
        $admin_id = session()->get('admin.id');
        $user_type = session()->get('admin.user_type');
        $user_name = session()->get('admin.user_name');
        if ($user_type == 1) {
            $admin_id = 1;
        }
        $verify = (int) $request->input('verify');
        if (!in_array($verify, [1, 2])) {
            return ['status'=>false, 'msg'=>'参数错误！'];
        }
        $wv = WarrantyVerify::findOrFail($id);
        $w = Warranty::findOrFail($wv->warranty_id);
        if ($wv->approved != 0) {
            return ['status'=>false, 'msg'=>'信息状态错误，请刷新重试，如多次出现请联系管理员！'];
        }
        if ($w->approved != 0) {
            return ['status'=>false, 'msg'=>'信息状态错误，请刷新重试，如多次出现请联系管理员！'];
        }
        $wv->approved = $verify;
        $wv->note = $request->input('note');
        $wv->updated_by	 = $admin_id;
        $wv->admin_name	 = $user_name;
        $wv->save();

        $w->approved = $verify;
        $w->approved_by_user_id = $admin_id;
        $w->approved_date = date('Y-m-d H:i:s');
        $w->save();

        $alogs = alogs('质保', '审核', $w->id, $wv, null);

        return ['status' => 'SUCCESS', 'msg' => '成功！'];
    }

    public function rolls_return_index(Request $request)
    {
        $user_id = session('admin.id');
        $user_type = session('admin.user_type');
        $page = [
            'page_name' => 'rolls',
            'title' => ts('MJTHGL'),
        ];
        $model = DB::table('roll_return as rt')
            ->leftJoin('roll_master as rm', 'rm.id', 'rt.roll_id')
            ->leftJoin('users as y', 'y.id', 'rt.user_id')
            ->leftJoin('users as c', 'c.id', 'rt.created_by')
            ->select(['rt.*', DB::raw('y.abbr as ygmsmc'), 'c.abbr as czy', 'rm.roll_number', 'rm.film_type_id']);
        $model = $this->filterByColumns($request, $model, ['rm.roll_number', 'y.abbr', 'x.abbr', 'c.abbr' ]);
        $model = filterByDateRange($model, 'rt.created_at');
        if ($request->input('status')!==null) {
            $status = (int) $request->input('status');
            $model = $model->where('rt.status', $status);
        }
        /*
         * 如果用户为管理员 */
        if ($user_type == 1) {
            $model = $model->where('c.id', 1);
        } else {
            $model = $model->where(function ($query) use ($user_id) {
                $query->where('y.id', $user_id)
                    ->orWhere('y.creator_id', $user_id);
            });
        }
        $model = $model->orderByDesc('rt.id');
        $data = $model->paginate();
        return view('admin.admin.rolls-return-index', [
            'data'=>$data,
            'page' => $page,
        ]);
    }

    /*
     * 发起退货*/
    public function rolls_return_create(Request $request)
    {
        $user_id = session('admin.id');
        $user_type = session('admin.user_type');
        if ($user_type != 2) {
            abort(404);
        }
        // checkAuthority(1);
        $page = [
            'page_name' => 'rolls',
            'title' => ts('fqth'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEPRODUCT'),
                    'url' => 'javascript:void(0)'
                ]
            ],
        ];
        $data = new RollMaster();
        return view('admin.admin.rolls-return-create', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    /*
     * 提交退货*/
    public function rolls_return_store(Request $request)
    {
        $admin_id = session('admin.id');
        $user_id = session('admin.id');
        $user_type = session('admin.user_type');
        if ($user_type != 2) {
            abort(404);
        }
        $roll_number = $request->input('roll_number');
        $roll = RollMaster::where('roll_number', $roll_number)
            ->firstOrFail();
        $rest = RollRestlen::where([
            'user_id' => $user_id,
            'roll_number' => $roll_number,
        ])->firstOrFail();
        if ($rest->restlen != $roll->length) {
            return ['status'=>false, 'msg'=>'仅允许整卷退货！'];
        }
        $model = new RollReturn();
        $model->roll_id = $roll->id;
        $model->user_id = $user_id;
        $model->length = $roll->length;
        $model->note = $request->input('note');
        $model->status = 0;
        $model->created_by = $admin_id;
        $model->save();
        $rest->restlen = 0;
        $rest->save();
        alogs('膜', '退货', $model->id, $model, null);
        return redirect('/admin/rolls')->with('trash', '已发起退货！');
    }
    /*
     * 膜卷退货管理*/
    public function verify_rolls_return(Request $request)
    {
        checkAuthority(1);
        $page = [
            'page_name' => 'rolls',
            'title' => ts('mjthgl'),
            'breadcrumb' => [
                [
                    'text' => ts('mjthgl'),
                    'url' => '/admin/verify/rolls_return'
                ]
            ],
        ];
        $model = DB::table('roll_return as rr')
            ->leftJoin('roll_master as rm', 'rm.id', 'rr.roll_id')
            ->leftJoin('users as x', 'x.id', 'rr.user_id')
            ->leftJoin('users as y', 'y.id', 'x.creator_id')
            ->leftJoin('users as z', 'z.id', 'rr.created_by');
        $model = filterByColumns($model, ['rm.roll_number', 'x.abbr', 'y.abbr', 'z.abbr']);
        $model = filterByDateRange($model, 'rr.created_at');

        $datas = $model->select(['rr.id', 'rr.status', 'rr.created_at', 'rm.id as rid', 'rm.roll_number', 'x.abbr as ygmsmc', DB::raw('if(x.user_type=3, y.abbr, x.abbr) as ysdmc'), 'z.abbr as fqr'])
            ->orderByDesc('id')
            ->paginate();
        return view('admin.admin.verify-rolls-return', [
            'page' => $page,
            'data' => $datas,
        ]);
    }
    public function verify_rolls_return_show(Request $request, $id)
    {
        checkAuthority(1);
        $page = [
            'page_name' => 'rolls',
            'title' => ts('xxck'),
            'breadcrumb' => [
                [
                    'text' => ts('mjthgl'),
                    'url' => '/admin/verify/rolls_return'
                ]
            ],
        ];
        $model = RollReturn::findOrFail($id);
        $data = DB::table('roll_return as rr')
            ->leftJoin('roll_master as rm', 'rm.id', 'rr.roll_id')
            ->leftJoin('users as x', 'x.id', 'rr.user_id')
            ->leftJoin('users as y', 'y.id', 'x.creator_id')
            ->leftJoin('users as z', 'z.id', 'rr.created_by')
            ->leftJoin('users as c', 'c.id', 'rr.updated_by')
            ->where('rr.id', $id)
            ->select(['rr.*', 'rm.roll_number', 'rm.film_type_id', 'rm.id as rid', 'x.abbr as ygmsmc', DB::raw('if(x.user_type=3, y.abbr, x.abbr) as ysdmc'), 'z.abbr as fqr', 'c.abbr as czr'])
            ->first();

        return view('admin.admin.verify-rolls-return-show', [
            'page' => $page,
            'data' => $data,
        ]);
    }

    public function verify_rolls_return_verify(Request $request, $id)
    {
        checkAuthority(1);
        $model = RollReturn::findOrFail($id);
        $alogs = alogs('膜', '退货审核', $model->id, null, $model);
        $status = (int) $request->input('verify');
        if (!in_array($status, [1, 2])) {
            return ['status'=>false, 'msg'=>'未知状态！'];
        }
        if ($model->status != 0) {
            return ['status'=>false, 'msg'=>'不可以重复审核！'];
        }
        $model->status = $status;
        $model->feedback = trim($request->input('note'));
        $model->updated_by = session('admin.id');
        $model->save();
        $alogs->new = $model;
        $alogs->save();
        $roll = RollMaster::find($model->roll_id);
        if (!empty($roll) && !empty($roll->roll_number)) {
            if ($status == 1) {
                plusRestlen(1, $roll->roll_number, $model->length);
                addRollSales($model->id, 1, $model->user_id, $model->length, 3);
            } elseif ($status == 2) {
                plusRestlen($model->user_id, $roll->roll_number, $model->length);
            }
        }
        return ['status'=>'SUCCESS', 'msg'=>'已完成审核！'];
    }

    /*
     * 套餐退货管理*/
    public function verify_precut_return(Request $request)
    {
        checkAuthority(1);
        $page = [
            'page_name' => 'precut',
            'title' => ts('tcthgl'),
        ];
        $model = DB::table('precut_assign as ps')
            ->leftJoin('precut as p', 'p.id', 'ps.precut_id')
            ->leftJoin('users as x', 'x.id', 'ps.sold_by_user_id')
            ->leftJoin('users as y', 'y.id', 'x.creator_id')
            ->leftJoin('users as z', 'z.id', 'ps.created_by')
            ->where('ps.type', 2);
        $model = filterByColumns($model, ['p.precut_id', 'x.abbr', 'y.abbr', 'z.abbr']);
        $model = filterByDateRange($model, 'ps.created_at');

        $datas = $model->select(['ps.id', 'ps.created_at', 'p.id as pid', 'p.precut_id', 'x.abbr as ygmsmc', DB::raw('if(x.user_type=3, y.abbr, x.abbr) as ysdmc'), 'z.abbr as fqr'])
            ->orderByDesc('id')
            ->paginate();
        $ids = Arr::pluck($datas, 'pid');
        $coverage = DB::table('precut_to_vehicle_coverage')
            ->whereIn('precut_kit_sale_id', $ids)
            ->get();
        $pvc = [];
        foreach ($coverage as $k => $v) {
            if (empty($pvc[$v->precut_kit_sale_id])) {
                $pvc[$v->precut_kit_sale_id] = [];
            }
            $pvc[$v->precut_kit_sale_id][] = $v;
        }

        return view('admin.admin.verify-precut-return', [
            'page' => $page,
            'data' => $datas,
            'coverages' => $pvc,
        ]);

    }


    protected function getUserResidueLengthByRollId($id)
    {
        $user_id = session('admin.id'); // 获取操作用户属性
        /*$roll = DB::table('roll_master')->where('roll_number', $q)->first();
        if (empty($roll) || empty($roll->id)) {
            return false;
        }*/
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
        /*if ($user_id == 1) {
            $sold_to += $roll->length;
        }*/
        return $sold_to - $sold_by;
    }

    public function warranty_index(Request $request)
    {
        $page = [
            'page_name' => 'warranty',
            'title' => ts('MANAGEWARRANTY'),
        ];
        $user_id = session('admin.id');
        $user_level = session('admin.user_type');
        $db = DB::table('warranty', 'w')
            ->whereNull('w.deleted_at')
            ->leftJoin('users as u', 'w.user_id', '=', 'u.id')
            ->leftJoin('warranty_to_vehicle_coverage as wvc', 'w.id', '=', 'wvc.warranty_id')
            ->groupBy('w.id');

        if ($user_level == 2) {
            $db = $db->where(function ($query) use ($user_id) {
                $query->orWhere('u.id', $user_id)
                    ->orWhere('u.creator_id', $user_id)
                    ->orWhere(function ($q2) use ($user_id) {
                        $q2->where('w.user_id', 0)
                            ->where('w.created_by', $user_id);
                    });
            });
        }
        $sorts = [
            'warranty_id' => 'w.warranty_id',
            'installation_date' => 'w.installation_date',
            'first_name' => 'w.first_name',
            'approved' => 'w.approved',
            'license_plate' => 'w.license_plate',
            'abbr' => 'u.abbr',
            'roll_number' => 'wvc.roll_number',
            'wvclen' => 'wvclen'
        ];
        $db = sortByColumn($db, $sorts);

        // $db = orderByCol($db, ['w.warranty_id', 'u.abbr', 'u.company_name', 'w.license_plate', 'w.installation_date', 'w.installer_name', 'wvc.roll_number']);
        $db = $this->filterByColumns($request, $db, ['w.warranty_id', 'w.first_name', 'u.abbr', 'u.company_name', 'w.license_plate', 'w.installation_date', 'w.installer_name', 'wvc.roll_number']);

        $startdate = trim($request->input('startdate'));
        $enddate = trim($request->input('enddate'));
        if ($startdate) {
            $db = $db->where('installation_date', '>=', $startdate);
        }
        if ($enddate) {
            $db = $db->where('installation_date', '<=', $enddate);
        }
        if ($request->input('status') !== null) {
            $db = $db->where('approved', $request->input('status'));
        }
        $db = $db->orderByDesc('id');

        $data = $db->select(['w.*', 'u.company_name', 'u.abbr', DB::raw('sum(wvc.length) as wvclen')])
            ->paginate();
        $wvc = DB::table('warranty_to_vehicle_coverage')->whereIn('warranty_id', Arr::pluck($data, 'id'))->get();
        $wvcs = [];
        foreach ($wvc as $w) {
            if (empty($wvcs[$w->warranty_id])) {
                $wvcs[$w->warranty_id] = [];
            }
            $wvcs[$w->warranty_id][] = $w;
        }
        foreach ($data as $k => $v) {
            $v->warranty_to_vehicle_coverage = @$wvcs[$v->id] ?: [];
            $v->status_zh = '审核';
        }
        return view('admin.admin.warranty-index', [
            'data'=> $data,
            'page' => $page,
        ]);
    }
    public function warranty_show(Request $request, $id)
    {
        $user_id = session('admin.id');
        $user_type = session('admin.user_type');
        $page = [
            'page_name' => 'warranty',
            'title' => ts('VIEWWARENTY'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEWARRANTY'),
                    'url' => '/admin/warranty'
                ]
            ],
        ];
        $data = Warranty::findOrFail($id);
        if ($user_type!=1) {
            if (empty($data->user_id)) {
                if ($data->created_by != $user_id) {
                    abort(404);
                }
            } else {
                $user = Admin::withTrashed()->find($data->user_id);
                if ($data->user_id != $user_id && @$user->creator_id != $user_id) {
                    abort(404);
                }
            }
        }
        $images = Warranty::where('warranty_id', $data->id)->get();
        return view('admin.admin.warranty-show', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    public function warranty_create(Request $request)
    {
        $page = [
            'page_name' => 'warranty',
            'title' => ts('ADDWARENTY'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEWARRANTY'),
                    'url' => '/admin/warranty'
                ]
            ],
        ];
        $data = new Warranty();
        return view('admin.admin.warranty-create', [
            'data'=>$data,
            'page' => $page,
        ]);
    }
    public function warranty_store(Request $request)
    {
        // return $request->input();
        $usid = (int) $request->input('user_id');
        $admin_id = session('admin.id');
        if (empty($usid) || !checkDealerBelongs($usid)) {
            return redirect()->back()->withInput()->with('trash', ['content'=>'仅可以选择自己或下属经销商']);
        }
        /*$udts = cmics();
        if (@$udts['status'] != 'SUCCESS') {
            return rs4(@$udts['msg']);
        }
        $udts = $udts['data'];*/

        $model = new Warranty();
        $model->user_id = $usid;
        $model->first_name = $request->input('first_name') ?: '';
        $model->address = $request->input('address') ?: '';
        $model->region_id = $request->input('region_id') ?: '';
        $model->province_id = $request->input('province_id') ?: '';
        $model->city = $request->input('city') ?: '';
        $model->zip = $request->input('zip') ?: '';
        $model->email_address = $request->input('email_address') ?: '';
        $model->extension = $request->input('extension') ?: '';
        $model->phone_number = $request->input('phone_number') ?: '';
        $model->year_id = $request->input('year_id') ?: '';
        $model->make_id = $request->input('make_id') ?: '';
        $model->model_id = $request->input('model_id') ?: '';
        $model->license_plate = $request->input('license_plate') ?: '';
        $model->vin_number = $request->input('vin_number') ?: '';
        $model->installer_name = $request->input('installer_name') ?: '';
        $model->installation_date = $request->input('installation_date') ?: '';
        $model->installation_price = $request->input('installation_price') ?: '';
        /*$model->image_front = $request->input('image_front') ?: '';
        $model->image_back = $request->input('image_back') ?: '';
        $model->image_driver_side = $request->input('image_driver_side') ?: '';
        $model->image_passenger_side = $request->input('image_passenger_side') ?: '';*/
        $model->additional_comments = $request->input('additional_comments') ?: '';
        $model->created_by = $admin_id;
        $model->warranty_id = makeWarrantyId($model->installation_date);
        $model->approved = 3;

        $model->save();

        $uploads = [];
        $dtime = date('Y-m-d H:i:s');
        $udts = wazbw();
        foreach ($udts as $k => $v) {
            $v['warranty_id'] = $model->id;
            $v['created_at'] = $dtime;
            $v['updated_at'] = $dtime;
            $uploads[] = $v;
        }

        $c = DB::table('warranty_to_vehicle_coverage')->where('warranty_id', $model->id);
        $cs = $c->get();
        foreach ($cs as $k => $v) {
            // plusRestlen($usid, $v->roll_number, $v->length);
        }
        $b = $c->delete();
        $b = DB::table('warranty_to_vehicle_coverage')->insert($uploads);
        foreach ($uploads as $k => $v) {
            // minusRestlen($usid, $v['roll_number'], $v['length']);
        }

        // $images = DB::table('warranty_image')->where('warranty_id', $model->id)->get();
        // DB::table('warranty_image')->where('warranty_id', $model->id)->delete();
        $imgs = [];
        $rmg = $request->input('images');
        if ($rmg && is_array($rmg)) {
            foreach ($rmg as $k => $v) {
                $item = [];
                $item['warranty_id'] = $model->id;
                $item['created_at'] = $dtime;
                $item['updated_at'] = $dtime;
                $item['image_src'] = $v;
                $item['part_id'] = $k+1;
                $imgs[] = $item;
            }
        }
        if ($imgs) {
            DB::table('warranty_image')->insert($imgs);
        }

        $alogs = alogs('质保', '添加', $model->id, mmc($model, $uploads, $imgs), null);
        $url = '/admin/warranty';
        session()->flash('hightlight', ['id'=>$model->id, 'type'=>'warranty']);
        return redirect($url)->with('trash', ts('WARRENTYADDSUCCESS'));
    }

    /*
     * 当尚未分配渠道时（一般不存在），判断创建人是否是当前用户*/
    public function warranty_edit(Request $request, $id)
    {
        $model = Warranty::findOrFail($id);
        $ut = session('admin.user_type');
        $uid = session('admin.id');
        if (empty($model->warranty_id)) {
            $model->warranty_id = makeWarrantyId($model->installation_date);
            $model->save();
        }
        if ($model->approved == 1) {
            return redirect('/admin/warranty/')->with('trash', ['content'=>'已通过数据无法修改！', 'type'=>'error']);
        }
        if ($ut != 1) {
            if ($model->approved !=3 && $model->approved != 2) {
                return redirect('/admin/warranty/')->with('trash', ['content'=>'已提交审核数据无法修改！', 'type'=>'error']);
            }

            if (!empty($model->user_id)) {
                if (!checkDealerBelongs($model->user_id)) {
                    return redirect('/admin/warranty/')->with('trash', ['content'=>'未知数据！', 'type'=>'error']);
                }
            } else {
                if (!($model->created_by == $uid)) {
                    return redirect('/admin/warranty/')->with('trash', ['content'=>'未知数据！', 'type'=>'error']);
                }
            }
        }
        $page = [
            'page_name' => 'warranty',
            'title' => ts('EDITWARENTY'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEWARRANTY'),
                    'url' => '/admin/warranty'
                ]
            ],
        ];

        return view('admin.admin.warranty-edit', [
            'data'=>$model,
            'page' => $page,
        ]);
    }

    public function warranty_update(Request $request, $id)
    {
        $model = Warranty::findOrFail($id);
        $ut = session('admin.user_type');
        $uid = session('admin.id');
        $admin_id = session('admin.id');
        if ($model->approved == 1) {
            return redirect('/admin/warranty/')->with('trash', ['content'=>'已通过数据无法修改！', 'type'=>'error']);
        }
        if ($ut != 1) {
            if ($model->approved !=3 && $model->approved != 2) {
                return redirect()->back()->with('trash', ['content'=>'已提交审核数据无法修改！', 'type'=>'error']);
            }
            if (!empty($model->user_id)) {
                if (!checkDealerBelongs($model->user_id)) {
                    return redirect()->back()->with('trash', ['content'=>'未知数据！', 'type'=>'error']);
                }
            } else {
                if (!($model->created_by == $uid)) {
                    return redirect()->back()->with('trash', ['content'=>'未知数据！', 'type'=>'error']);
                }
            }
        }

        $model->first_name = $request->input('first_name') ?: '';
        $model->address = $request->input('address') ?: '';
        $model->region_id = $request->input('region_id') ?: '';
        $model->province_id = $request->input('province_id') ?: '';
        $model->city = $request->input('city') ?: '';
        $model->zip = $request->input('zip') ?: '';
        $model->email_address = $request->input('email_address') ?: '';
        $model->extension = $request->input('extension') ?: '';
        $model->phone_number = $request->input('phone_number') ?: '';

        $model->license_plate = $request->input('license_plate') ?: '';
        $model->vin_number = $request->input('vin_number') ?: '';
        $model->installer_name = $request->input('installer_name') ?: '';
        $model->installation_date = $request->input('installation_date') ?: '';
        $model->installation_price = $request->input('installation_price') ?: '';
        /*$model->image_front = $request->input('image_front') ?: '';
        $model->image_back = $request->input('image_back') ?: '';
        $model->image_driver_side = $request->input('image_driver_side') ?: '';
        $model->image_passenger_side = $request->input('image_passenger_side') ?: '';*/
        $model->additional_comments = $request->input('additional_comments') ?: '';
        $model->updated_by = $admin_id;

        $model->year_id = $request->input('year_id') ?: '';
        $model->make_id = $request->input('make_id') ?: '';
        $model->model_id = $request->input('model_id') ?: '';

        $wvcs = DB::table('warranty_to_vehicle_coverage')->where('warranty_id', $model->id)->get(); // 质保使用的产品数据
        $images = DB::table('warranty_image')->where('warranty_id', $model->id)->get(); // 质保安装图片
        $alogs = alogs('质保', '修改', $model->id, null, mmc($model, $wvcs, $images));
        unset($model->coverages);
        unset($model->images);
        $uploads = [];
        $dtime = gDate();
        if (empty($model->pre_id)) {
            $usid = (int) $request->input('user_id');
            if (empty($usid) || !checkDealerBelongs($usid)) {
                return redirect()->back()->withInput()->with('trash', ['content'=>'仅可以选择自己或下属经销商']);
            }
            $model->user_id = $usid;
            /*$udts = cmics();
            if (@$udts['status'] != 'SUCCESS') {
                return rs4(@$udts['msg']);
            }
            $udts = $udts['data'];*/
            $udts = wazbw();
            foreach ($udts as $k => $v) {
                $v['warranty_id'] = $model->id;
                $v['created_at'] = $dtime;
                $v['updated_at'] = $dtime;
                $uploads[] = $v;
            }
            $cs = DB::table('warranty_to_vehicle_coverage')->where('warranty_id', $model->id)->get();
            foreach ($cs as $k => $v) {
                // plusRestlen($usid, $v->roll_number, $v->length); // 发起审核时再做处理
            }
            DB::table('warranty_to_vehicle_coverage')->where('warranty_id', $model->id)->delete();
            $b = DB::table('warranty_to_vehicle_coverage')->insert($uploads);
            /*
             * 当状态为2时，表示该质保曾发起过审核，所需需要减少长度*/
            if ($model->approved == 2) {
                foreach ($uploads as $k => $v) {
                    plusRestlen($model->user_id, $v['roll_number'], $v['length']); // 发起审核时再做处理
                }
            }

        } else {
            $uploads = $wvcs;
        }
        if ($model->approved == 2) {
            $model->approved = 3; // 管理员也可以修改，所以仅当状态为2时，更新为3
        }
        /*安装图片*/
        DB::table('warranty_image')->where('warranty_id', $model->id)->delete();
        $imgs = [];
        $rmg = $request->input('images');
        if ($rmg && is_array($rmg)) {
            foreach ($rmg as $k => $v) {
                $item = [];
                $item['warranty_id'] = $model->id;
                $item['created_at'] = $dtime;
                $item['updated_at'] = $dtime;
                $item['image_src'] = $v;
                $item['part_id'] = $k+1;
                $imgs[] = $item;
            }
        }
        if ($imgs) {
            DB::table('warranty_image')->insert($imgs);
        }
        // $model->approved = 3;
        $model->save();
        $msgModel = WarrantyMsg::where('warranty_id', $model->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->first();
        if (!empty(@$msgModel->id)) {
            $msgModel->status = 1;
            $msgModel->save();
        }



        $alogs->new = mmc($model, $uploads);
        $alogs->save();

        $url = '/admin/warranty';
        session()->flash('hightlight', ['id'=>$model->id, 'type'=>'warranty']);
        return redirect($url)->with('trash', ts('WARRENTYEDITSUCCESS'));
    }
    /*仅管理员可以删除、编辑修改*/
    /*
     * 非管理员可以删除待提交或者被拒绝数据
     * 产品表仅需要根据质保ID查询
     * 主判断满足条件下的质保是否存在，假如存在则删除，否则不删除*/
    public function warranty_delete(Request $request, $id)
    {
        return deleteWarranty($id);
        return ['status' => 'SUCCESS', 'msg' => '成功！'];
    }
    public function warranty_deletes(Request $request)
    {
        $ids = $request->input('id');
        if (empty($ids)) {
            return ['status'=>false, 'msg'=>'参数错误！'];
        }
        foreach ($ids as $k => $id) {
            deleteWarranty($id);
        }
        return ['status' => 'SUCCESS', 'msg' => '成功！'];
    }

    public function warranty_download_template(Request $request)
    {
        $file= storage_path('app/template/warranty_template_2020.xlsx');
        $filename = 'warranty_template.xlsx';
        return response()->download($file, $filename);
    }
    public function warranty_bulk_import(Request $request)
    {
        # return [$this->getPartIdByName('LUX'), $this->getPartIdByName('Fender'), $this->getPartIdByName('後葉子板'), $this->getPartIdByName('侧裙')];
        $page = [
            'page_name' => 'warranty',
            'title' => ts('bulkimport'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEWARRANTY'),
                    'url' => '/admin/warranty'
                ]
            ],
        ];
        return view('admin.users.warranty_bulk_import', [
            'page' => $page,
        ]);
    }
    public function warranty_handle_import(Request $request)
    {
        $user_id = session('admin.id');
        $file = $request->file('file');
        if (empty($file)) {
            return redirect()->back()->withInput()->with('trash', ['content'=>'文件不存在！', 'type'=>'error']);
        }
        if ($file->getClientOriginalExtension() != 'xlsx') {
            return redirect()->back()->withInput()->with('trash', ['content'=>'仅支持上传xlsx类型文件', 'type'=>'error']);
        }
        $filename = Str::random(16) . '.xlsx';
        $path = $this->getFileStorePath(2);
        // file_put_contents($path['base'] . $filename, );
        $bool = $file->storeAs($path['path'], $filename, 'storage');
        $full_file_name = $path['path'].$filename; // /upload/XXX/filename
        $mod = new FileUpload();
        $mod->type = 2;
        $mod->name = '质保文件上传';
        $mod->user_id = $user_id;
        $mod->file = $full_file_name;
        $mod->save();
        $data = $this->parseWarrantyExcelFile($path['base'] . $filename);
        return redirect('/admin/warranty/'.$data->id)->with('trash', '导入成功！');
    }

    public function logs_index(Request $request)
    {
        checkAuthority(1);
        $page = [
            'page_name' => 'monitor',
            'title' => ts('OPERATIONRECORDS'),
            'breadcrumb' => [
                [
                    'text' => ts('MONITORINGCENTER'),
                    'url' => '/admin/logs'
                ]
            ],
        ];
        // $model = DB::table('activity_logs');
        $model = DB::table('activity as a');
        $model = filterByDateRange($model, 'a.created_at');
        $model->leftJoin('users', 'users.id', 'a.user_id');
        $model = $this->filterByColumns($request, $model, ['user_id', 'module', 'activity', 'abbr', 'company_name']);
        $model->select(['a.id', 'a.module', 'a.user_name', 'a.activity', 'a.record', 'a.new', 'a.old', 'a.created_at', 'users.abbr']);
        $model = $model->orderByDesc('id');
        $data = $model->paginate(50);
        return view('admin.admin.logs-index', [
            'page' => $page,
            'data' => $data
        ]);
    }

    public function dblogs_index(Request $request)
    {
        checkAuthority(1);
        $page = [
            'page_name' => 'monitor',
            'title' => ts('OPERATIONRECORDS'),
            'breadcrumb' => [
                [
                    'text' => ts('MONITORINGCENTER'),
                    'url' => '/admin/logs'
                ]
            ],
        ];
        $model = DB::table('db_logs as d')
            ->orderByDesc('id')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->select(['d.*', 'u.username', 'u.abbr']);

        $model = filterByColumns($model, ['d.user_id', 'd.operation', 'd.table_name', 'd.query', 'u.abbr', 'u.company_name']);
        $model = filterByDateRange($model, 'd.created_at');
        $datas = $model->orderByDesc('id')->paginate(50);
        return view('admin.admin.dblogs-index', [
            'page' => $page,
            'data' => $datas
        ]);
    }

    public function precut_index(Request $request)
    {
        // checkAuthority(1);
        $admin_id = session('admin.id');
        $user_type = session('admin.user_type');
        if ($user_type == 1) {
            $admin_id = 1;
        }
        $page = [
            'page_name' => 'precut',
            'title' => ts('MANAGEPRECUT'),
        ];
        $model = DB::table('precut as p')
            ->leftJoin('users as u', 'u.id', '=', 'p.user_id')
            ->leftJoin('warranty as w', 'w.pre_id', 'p.id')
            ->whereNull('p.deleted_at')
            ->whereNull('w.deleted_at')
            ->orderByDesc('p.id')
            ->select(['p.*', 'u.abbr as jxsmc', 'w.first_name', 'w.phone_number', 'w.installation_date']);
        $model = filterByColumns($model, ['p.id', 'p.precut_id', 'u.abbr', 'w.first_name', 'w.phone_number']);
        $model = filterByDateRange($model, 'p.created_at');
        if ($request->input('status')!==null) {
            $model = $model->where('p.status', (int)$request->input('status'));
        }
        if ($user_type != 1) {
            $model = $model->whereIn('p.user_id', function ($query) use ($admin_id) {
                $query->select('id')->from('users')
                    ->where('id', $admin_id)
                    ->orWhere('creator_id', $admin_id);
            });
        }
        $datas = $model->paginate();
        $ids = Arr::pluck($datas, 'id');
        $coverage = DB::table('precut_to_vehicle_coverage')
            ->whereIn('precut_kit_sale_id', $ids)
            ->get();
        $pvc = [];
        foreach ($coverage as $k => $v) {
            if (empty($pvc[$v->precut_kit_sale_id])) {
                $pvc[$v->precut_kit_sale_id] = [];
            }
            $pvc[$v->precut_kit_sale_id][] = $v;
        }
        return view('admin.admin.precut-index', [
            'page' => $page,
            'data' => $datas,
            'coverages' => $pvc,
        ]);
    }

    public function precut_show(Request $request, $id)
    {
        checkAuthority(1);
        $page = [
            'page_name' => 'precut',
            'title' => ts('XXCK'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEPRECUT'),
                    'url' => '/admin/precut'
                ]
            ],
        ];
        $model = Precut::findOrFail($id);
        $warranty = Warranty::where('pre_id', $id)->first();
        if (empty($warranty)) {
            $warranty = new Warranty();
        }
        $assign = PrecutAssign::where('precut_id', $model->id)->orderByDesc('id')->get();
        return view('admin.admin.precut-show', [
            'page' => $page,
            'data' => $model,
            'warranty' => $warranty,
            'assign' => $assign
        ]);
    }

    public function precut_create(Request $request)
    {
        checkAuthority();
        $page = [
            'page_name' => 'precut',
            'title' => ts('TJXX'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEPRECUT'),
                    'url' => '/admin/precut'
                ]
            ],
        ];
        $data = new Precut();
        $pid = $this->makePid();
        $data->precut_id = $pid;
        return view('admin.admin.precut-create', [
            'data'=>$data,
            'page' => $page,
        ]);
    }

    protected function makePid()
    {
        // 'PREC1000000'
        $model = Sequences::firstOrNew([
            'table' => 'precut'
        ]);
        $model->next = $model->next ?: 1;
        $pid = $model->next;
        $pid = 1000000 + $pid;
        $pid = 'PREC'.$pid;
        $model->next = $model->next + 1;
        $model->save();
        return $pid;
    }

    protected function checkPVC(Request $request)
    {
        $user_id = session('admin.id');
        $user_type = session('admin.user_type');
        if ($user_type == 1) {
            $user_id = 1;
        }
        $ups = cleanCoverages();
        $udts = checkPartItems($ups, $user_id);
        return $udts;
    }

    public function precut_store(Request $request)
    {
        checkAuthority();
        $admin_id = session('admin.id');
        $user_type = session('admin.user_type');
        if ($user_type == 1) {
            $admin_id = 1;
        }
        $udts = $this->checkPVC($request);
        if (@$udts['status'] != 'SUCCESS') {
            return rs4(@$udts['msg']);
        }
        $udts = $udts['data'];

        $user_id = $request->input('user_id');

        $model = new Precut();
        $precut_id = $request->input('precut_id');
        $model->precut_id = $precut_id;
        /*$model->year_id = $request->input('year_id');
        $model->make_id = $request->input('make_id');
        $model->model_id = $request->input('model_id');
        $model->user_id = $user_id;*/
        $model->note = $request->input('note');
        $model->created_by = $admin_id;

        $model->save();

        $uploads = [];
        foreach ($udts as $k => $v) {
            $v['precut_kit_sale_id'] = $model->id;
            $v['created_at'] = date('Y-m-d H:i:s');
            $v['updated_at'] = date('Y-m-d H:i:s');
            $uploads[] = $v;
        }
        $c = DB::table('precut_to_vehicle_coverage')->where('precut_kit_sale_id', $model->id);
        $cs = $c->get();
        $b = $c->delete();
        foreach ($cs as $k => $v) {
            plusRestlen($admin_id, $v->roll_number, $v->length);
        }
        $b = DB::table('precut_to_vehicle_coverage')->insert($uploads);
        foreach ($uploads as $k => $v) {
            minusRestlen($admin_id, $v['roll_number'], $v['length']);
        }
        $alogs = alogs('套餐', '添加', $model->id, mmc($model, $uploads), null);

        $url = $request->input('_previous_') ?: '/admin/precut';
        $url = '/admin/precut';
        session()->flash('hightlight', ['id'=>$model->id, 'type'=>'precut']);
        return redirect($url)->with('trash', ts('precutADDSUCCESS'));
    }

    public function precut_edit(Request $request, $id)
    {
        checkAuthority();
        $page = [
            'page_name' => 'precut',
            'title' => ts('BJXX'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEPRECUT'),
                    'url' => '/admin/precut'
                ]
            ],
        ];
        $data = Precut::findOrFail($id);
        return view('admin.admin.precut-edit', [
            'data'=>$data,
            'page' => $page,
        ]);
    }


    public function precut_update(Request $request, $id)
    {
        checkAuthority();
        $admin_id = session('admin.id');
        $user_type = session('admin.user_type');
        if ($user_type == 1) {
            $admin_id = 1;
        }
        $udts = $this->checkPVC($request);
        if (@$udts['status'] != 'SUCCESS') {
            return rs4(@$udts['msg']);
        }
        $udts = $udts['data'];

        $user_id = $request->input('user_id');

        $model = $data = Precut::findOrFail($id);
        $c = DB::table('precut_to_vehicle_coverage')->where('precut_kit_sale_id', $model->id);
        $cs = $c->get();
        $w = Warranty::where('pre_id', $id)->first();
        if (empty($w)) {
            $alogs = alogs('套餐', '修改', $model->id, null, mpmc($model, $cs, $w));
            unset($model->coverages);
            unset($model->warranty);
        } else {
            $wvc = DB::table('warranty_to_vehicle_coverage')->where('warranty_id', $w->id)->get();
            $alogs = alogs('套餐', '修改', $model->id, null, mpmc($model, $cs, mmc($w, $wvc)));
        }
        $model->precut_id = $model->precut_id ?: $this->makePid();
        $model->updated_by = $admin_id;

        $model->save();

        $uploads = [];
        foreach ($udts as $k => $v) {
            $v['precut_kit_sale_id'] = $model->id;
            $v['created_at'] = date('Y-m-d H:i:s');
            $v['updated_at'] = date('Y-m-d H:i:s');
            $uploads[] = $v;
        }
        $b = $c->delete();
        foreach ($cs as $k => $v) {
            plusRestlen($model->created_by, $v->roll_number, $v->length);
        }
        $b = DB::table('precut_to_vehicle_coverage')->insert($uploads);
        foreach ($uploads as $k => $v) {
            minusRestlen($admin_id, $v['roll_number'], $v['length']);
        }

        if (!empty($w)) {
            $w->user_id = $model->user_id;
            $w->save();
            DB::table('warranty_to_vehicle_coverage')->where('warranty_id', $w->id)->delete();
            $uw = [];
            foreach ($udts as $k => $v) {
                $v['warranty_id'] = $w->id;
                $v['created_at'] = date('Y-m-d H:i:s');
                $v['updated_at'] = date('Y-m-d H:i:s');
                $uw[] = $v;
            }
            DB::table('warranty_to_vehicle_coverage')->insert($uw);
            $alogs->new = mpmc($model, $cs, mmc($w, $uw));
        } else {
            $alogs->new = mpmc($model, $cs, $w);
        }
        $alogs->save();

        $url = $request->input('_previous_') ?: '/admin/precut';
        $url ='/admin/precut';
        session()->flash('hightlight', ['id'=>$model->id, 'type'=>'precut']);
        return redirect($url)->with('trash', ts('precutEditSUCCESS'));
    }
    /*
     * 套餐分配
     * 每次分配时重设置主表用户*/
    public function precut_assign(Request $request)
    {
        $admin_id = session('admin.id');
        $user_type = session('admin.user_type');
        $id = $request->input('id');
        $user_id = $request->input('user_id');
        $model = Precut::findOrFail($id);
        if ($model->status == 1) {
            return ['status' => false, 'msg' => '套餐已安装，无法重新分配'];
        }
        if ($user_type == 1) {
            if (!empty($model->user_id) && $model->user_id != 1) {
                return ['status' => false, 'msg' => '无分配权限'];
            }
        } else {
            if ($model->user_id != $admin_id) {
                return ['status' => false, 'msg' => '无分配权限'];
            }
        }
        assignPrecut($id, $admin_id, $user_id);
        $model->user_id = $user_id;
        $model->save();
        session()->flash('hightlight', ['id'=>$model->id, 'type'=>'precut']);
        return ['status'=>'SUCCESS', 'msg' => '成功！'];
    }
    public function precut_return(Request $request)
    {
        $admin_id = session('admin.id');
        $user_type = session('admin.user_type');
        $id = $request->input('id');
        $model = Precut::findOrFail($id);
        if ($model->status == 1) {
            return ['status'=>false, 'msg'=>'已安装套餐无法退货！'];
        }
        if (!$model->user_id) {
            return ['status'=>false, 'msg'=>'套餐尚未分配，无需退货！'];
        }
        if ($user_type == 1) {
            return ['status'=>false, 'msg'=>'不允许管理员退货！'];
        }
        if (!checkDealerBelongs($model->user_id)) {
            return ['status'=>false, 'msg'=>'无权限操作该套餐！'];
        }
        assignPrecut($id, $model->user_id, 0, 2);
        $model->user_id = 0;
        $model->save();
        return ['status'=>'SUCCESS', 'msg' => '成功！'];
    }

    public function precut_delete(Request $request, $id)
    {
        return deletePrecut($id);
    }
    public function precut_deletes(Request $request)
    {
        $ids = $request->input('id');
        if (empty($ids)) {
            return ['status'=>false, 'msg'=>'参数错误！'];
        }
        foreach ($ids as $k => $id) {
            deletePrecut($id);
        }
        return ['status' => 'SUCCESS', 'msg' => '成功！'];
    }
    /*
     * 管理员查看所有
     * 省代查看分配给自己或经销商、且尚未安装的套餐*/
    public function precut_deal_index(Request $request)
    {
        $page = [
            'page_name' => 'precut_deal',
            'title' => ts('DANTC'),
        ];
        $admin_id = session('admin.id');
        $user_type = session('admin.user_type');
        if ($user_type == 1) {
            $admin_id = 1;
        }
        $model = DB::table('precut as p')
            ->where('p.status', 0)
            ->whereNull('p.deleted_at')
            ->leftJoin('users as u', 'u.id', 'p.user_id');
        if ($user_type != 1) {
            $model = $model->whereIn('user_id', function ($query) use ($admin_id) {
                $query->select('id')->from('users')
                    ->where('id', $admin_id)
                    ->orWhere('creator_id', $admin_id);
            });
        }
        $model = filterByColumns($model, ['p.precut_id', 'u.abbr']);
        $model = filterByDateRange($model, 'p.created_at');
        $model = $model->orderByDesc('id')
            ->select(['p.*', 'u.abbr as jxsmc']);
        $datas = $model->paginate(50);
        $ids = Arr::pluck($datas, 'id');
        $coverage = DB::table('precut_to_vehicle_coverage')
            ->whereIn('precut_kit_sale_id', $ids)
            ->get();
        $pvc = [];
        foreach ($coverage as $k => $v) {
            if (empty($pvc[$v->precut_kit_sale_id])) {
                $pvc[$v->precut_kit_sale_id] = [];
            }
            $pvc[$v->precut_kit_sale_id][] = $v;
        }

        return view('admin.admin.precut-deal-index', [
            'page' => $page,
            'data' => $datas,
            'coverages' => $pvc,
        ]);
    }

    public function warranty_install(Request $request)
    {
        $page = [
            'page_name' => 'precut',
            'title' => ts('installer'),
            'breadcrumb' => [
                [
                    'text' => ts('DANTC'),
                    'url' => '/admin/precut_deal'
                ]
            ],
        ];
        $data = new Warranty();
        $coverages = [];
        return view('admin.admin.warranty-install', [
            'page' => $page,
            'data' => $data, // $model
            'coverages' => $coverages,
        ]);
    }
    /*
     * 管理员无法使用套餐安装*/
    public function warranty_handle_install(Request $request)
    {
        $admin_id = session('admin.id');
        $user_type = session('admin.user_type');
        if ($user_type == 1) {
            $admin_id = 1;
            abort(404);
        }
        $id = $request->input('pre_id');
        $model = Precut::findOrFail($id);
        if (!checkDealerBelongs($model->user_id)) {
            abort(404);
        }

        // $w = new Warranty();
        $w = Warranty::firstOrNew(['pre_id' => $model->id]);
        /*套餐信息*/
        $w->pre_id = $model->id;
        $w->user_id = $model->user_id;
        $w->year_id = $model->year_id;
        $w->make_id = $model->make_id;
        $w->model_id = $model->model_id;
        /*提交信息*/
        $w->first_name = $request->input('first_name') ?: '';
        $w->address = $request->input('address') ?: '';
        $w->region_id = $request->input('region_id') ?: '';
        $w->province_id = $request->input('province_id') ?: '';
        $w->city = $request->input('city') ?: '';
        $w->zip = $request->input('zip') ?: '';
        $w->email_address = $request->input('email_address') ?: '';
        $w->extension = $request->input('extension') ?: '';
        $w->phone_number = $request->input('phone_number') ?: '';
        $w->license_plate = $request->input('license_plate') ?: '';
        $w->vin_number = $request->input('vin_number') ?: '';
        $w->installer_name = $request->input('installer_name') ?: '';
        $w->installation_date = $request->input('installation_date') ?: '';
        $w->installation_price = $request->input('installation_price') ?: '';
       /* $w->image_front = $request->input('image_front') ?: '';
        $w->image_back = $request->input('image_back') ?: '';
        $w->image_driver_side = $request->input('image_driver_side') ?: '';
        $w->image_passenger_side = $request->input('image_passenger_side') ?: '';*/
        $w->additional_comments = $request->input('additional_comments') ?: '';
        $w->created_by = $admin_id;
        $w->warranty_id = makeWarrantyId($w->installation_date);
        $w->approved = 3;
        $w->save();
        $pvc = DB::table('precut_to_vehicle_coverage')
            ->where('precut_kit_sale_id', $model->id)
            ->select(['part_id','roll_number','film_type_id','width','length'])
            ->get();
        $uploads = [];
        foreach ($pvc as $k => $v) {
            $v->warranty_id = $w->id;
            $uploads[] = (array)$v;
        }
        DB::table('warranty_to_vehicle_coverage')->insert($uploads);

        $dtime = gDate();
        $images = DB::table('warranty_image')->where('warranty_id', $model->id)->get();
        DB::table('warranty_image')->where('warranty_id', $model->id)->delete();
        $imgs = [];
        $rmg = $request->input('images');
        if ($rmg && is_array($rmg)) {
            foreach ($rmg as $k => $v) {
                $item = [];
                $item['warranty_id'] = $w->id;
                $item['created_at'] = $dtime;
                $item['updated_at'] = $dtime;
                $item['image_src'] = $v;
                $item['part_id'] = $k+1;
                $imgs[] = $item;
            }
        }

        if ($imgs) {
            DB::table('warranty_image')->insert($imgs);
        }
        $alogs = alogs('质保', '安装', $model->id, mmc($w, $uploads, $imgs), null);

        $model->status = 1;
        $model->save();
        $url = '/admin/warranty';
        return redirect($url)->with('trash', ts('warrantyADDSUCCESS'));
    }

    public function precut_deal_install (Request $request, $id)
    {
        return redirect('/admin/warranty/install?pre_id='.$id);
        $page = [
            'page_name' => 'precut',
            'title' => ts('installer'),
            'breadcrumb' => [
                [
                    'text' => ts('DANTC'),
                    'url' => '/admin/precut_deal'
                ]
            ],
        ];
        $model = Precut::findOrFail($id);
        if (!checkDealerBelongs($model->user_id)) {
            abort(404);
        }
        $data = Warranty::firstOrNew(['pre_id'=>$model->id]);
        $data->pre_id = $model->id;
        $data->user_id = $model->user_id;
        $data->year_id = $model->year_id;
        $data->make_id = $model->make_id;
        $data->model_id = $model->model_id;

        $coverages = DB::table('precut_to_vehicle_coverage')
            ->where('precut_kit_sale_id', $id)
            ->get();

        return view('admin.admin.precut-deal-install', [
            'page' => $page,
            'data' => $data, // $model
            'coverages' => $coverages,
        ]);
    }

    public function precut_deal_installp(Request $request, $id)
    {
        $admin_id = session('admin.id');
        $user_type = session('admin.user_type');
        if ($user_type == 1) {
            $admin_id = 1;
        }
        $model = Precut::findOrFail($id);
        if (!checkDealerBelongs($model->user_id)) {
            abort(404);
        }

        // $w = new Warranty();
        $w = Warranty::firstOrNew(['pre_id' => $model->id]);
        /*套餐信息*/
        $w->pre_id = $model->id;
        $w->user_id = $model->user_id;
        $w->year_id = $model->year_id;
        $w->make_id = $model->make_id;
        $w->model_id = $model->model_id;
        /*提交信息*/
        $w->first_name = $request->input('first_name') ?: '';
        $w->address = $request->input('address') ?: '';
        $w->region_id = $request->input('region_id') ?: '';
        $w->province_id = $request->input('province_id') ?: '';
        $w->city = $request->input('city') ?: '';
        $w->zip = $request->input('zip') ?: '';
        $w->email_address = $request->input('email_address') ?: '';
        $w->extension = $request->input('extension') ?: '';
        $w->phone_number = $request->input('phone_number') ?: '';
        $w->license_plate = $request->input('license_plate') ?: '';
        $w->vin_number = $request->input('vin_number') ?: '';
        $w->installer_name = $request->input('installer_name') ?: '';
        $w->installation_date = $request->input('installation_date') ?: '';
        $w->installation_price = $request->input('installation_price') ?: '';
        /*$w->image_front = $request->input('image_front') ?: '';
        $w->image_back = $request->input('image_back') ?: '';
        $w->image_driver_side = $request->input('image_driver_side') ?: '';
        $w->image_passenger_side = $request->input('image_passenger_side') ?: '';*/
        $w->additional_comments = $request->input('additional_comments') ?: '';
        $w->created_by = $admin_id;
        $w->warranty_id = makeWarrantyId($w->installation_date);
        $w->approved = 3;
        $w->save();
        $pvc = DB::table('precut_to_vehicle_coverage')
            ->where('precut_kit_sale_id', $model->id)
            ->select(['part_id','roll_number','film_type_id','width','length'])
            ->get();
        $uploads = [];
        foreach ($pvc as $k => $v) {
            $v->warranty_id = $w->id;
            $uploads[] = (array)$v;
        }
        DB::table('warranty_to_vehicle_coverage')->insert($uploads);

        $dtime = gDate();
        $images = DB::table('warranty_image')->where('warranty_id', $model->id)->get();
        DB::table('warranty_image')->where('warranty_id', $model->id)->delete();
        $imgs = [];
        $rmg = $request->input('images');
        if ($rmg && is_array($rmg)) {
            foreach ($rmg as $k => $v) {
                $item = [];
                $item['warranty_id'] = $w->id;
                $item['created_at'] = $dtime;
                $item['updated_at'] = $dtime;
                $item['image_src'] = $v;
                $item['part_id'] = $k+1;
                $imgs[] = $item;
            }
        }
        if ($imgs) {
            DB::table('warranty_image')->insert($imgs);
        }
        $alogs = alogs('质保', '安装', $model->id, mmc($w, $uploads, $imgs), null);

        $model->status = 1;
        $model->save();
        $url = $request->input('_previous_') ?: '/admin/precut_deal';
        return redirect($url)->with('trash', ts('precutADDSUCCESS'));
    }

    public function charts_index(Request $request)
    {
        $admin_id = session('admin.id');
        $user_type = session('admin.user_type');
        if ($user_type == 1) {
            $admin_id = 1;
        }
        $page = [
            'page_name' => 'charts',
            'title' => ts('bbzx'),
        ];
        $year = $request->input('year');
        $year = $year ?: date('Y');
        $r = DB::table('region_master')->orderBy('id')->get('id');
        $region = Arr::pluck($r, 'id'); // 地区
        $tm = []; // 补零后的1-12
        foreach (range(1,12) as $v) {
            $k = str_pad($v, 2, 0, STR_PAD_LEFT);
            $tm[$k] = 0;
        }
        $c1 = []; // 安装数量，按地区划分

        $data = DB::select("SELECT date_format(installation_date,'%m') AS oaWonVEI,COUNT(id) AS tX1xYm5Q, region_id FROM warranty WHERE installation_date like '".$year."%' GROUP BY region_id,oaWonVEI");

        foreach ($region as $k => $v) {
            $c1[$v] = $tm; // 地区
        }
        foreach ($data as $k => $v) {
            if (empty($c1[$v->region_id])) {
                $c1[$v->region_id] = [];
            }
            $c1[$v->region_id][$v->oaWonVEI] = $v->tX1xYm5Q;
        }
        $c1_1 = [
            'name' => ts('region_master.1'),
            'type' => 'line',
            'color' => '#1891ff',
            'data' => array_values($c1[1]),
        ];
        $c1_2 = [
            'name' => ts('region_master.2'),
            'type' => 'line',
            'color' => '#f9cc14',
            'data' => array_values($c1[2]),
        ];
        $c1_3 = [
            'name' => ts('region_master.3'),
            'type' => 'line',
            'color' => '#13c2c2',
            'data' => array_values($c1[3]),
        ];
        $c1_4 = [
            'name' => ts('region_master.4'),
            'type' => 'line',
            'color' => '#2fc25b',
            'data' => array_values($c1[4]),
        ];
        $c1_xAxis = [];
        foreach (range(1,12) as $v) {
            $c1_xAxis[] = ts('MON'.$v);
        }
        $c1_legend = [ts('region_master.1'),ts('region_master.2'),ts('region_master.3'),ts('region_master.4')];
        $c5d = [];
        foreach ($c1 as $k => $v) {
            $c5d[] = [
                'name' => ts('region_master.'.$k),
                'value' => array_sum(array_values($v)),
            ];
        }
        $c5 = [
            'series' => $c5d,
            'title' => ts('XSQYFB'),
            'legend' => $c1_legend,
        ];

        $c3 = $this->chart3();

        $c4 = $this->chart401($c3);

        $c2 = $this->chart2();
        return view('admin.admin.charts-index', [
            'page' => $page,
            'charts' => [
                'c1' => [
                    'series' => [
                        $c1_1,
                        $c1_2,
                        $c1_3,
                        $c1_4,
                    ],
                    'title' => ts('AZSL'),
                    'legend' => $c1_legend,
                    'xAxis' => $c1_xAxis,
                ],
                'c2' => $this->chart4($c2),
                'c3' => $c3,
                'c4' => $c4,
                'c5' => $c5,
                'c6' => $this->chart6(),
            ]
        ]);
    }
    protected function chart2()
    {
        $year = request()->input('year') ?: date('Y');
        $d1 = DB::select("SELECT date_format(installation_date,'%m') AS oaWonVEI,COUNT(id) AS tX1xYm5Q FROM warranty WHERE installation_date like '".$year."%' and user_id in (select id from users where user_type=2) GROUP BY oaWonVEI");
        $d2 = DB::select("SELECT date_format(installation_date,'%m') AS oaWonVEI,COUNT(id) AS tX1xYm5Q FROM warranty WHERE installation_date like '".$year."%' and user_id in (select id from users where user_type=3) GROUP BY oaWonVEI");
        $c1 = $c2 = $this->getMon2();
        foreach ($d1 as $k => $v) {
            if (!isset($c1[$v->oaWonVEI])) {
                continue;
            }
            $c1[$v->oaWonVEI] = $v->tX1xYm5Q;
        }
        $c1_1 = [
            'name' => ts('distributor'),
            'type' => 'bar',
            //  'barWidth' => '20%',
            'color' => '#1891ff',
            'data' => array_values($c1),
        ];
        foreach ($d2 as $k => $v) {
            if (!isset($c2[$v->oaWonVEI])) {
                continue;
            }
            $c2[$v->oaWonVEI] = $v->tX1xYm5Q;
        }
        $c1_2 = [
            'name' => ts('dealer'),
            'type' => 'bar',
            // 'barWidth' => '20%',
            'color' => '#2fc25b',
            'data' => array_values($c2),
        ];

        $data = [
            'series' => [
                $c1_1,
                $c1_2
            ],
            'title' => ts('MXSTJ'),
            'legend' => [ts('distributor'), ts('dealer')],
            'xAxis' => $this->getMon1(),
        ];

        return $data;
    }
    protected function chart401($data)
    {
        // $series = $data['series'];
        $c6d = $data['series'];
        $c4s = [];
        foreach ($c6d as $k => $v) {
            $v['type'] = 'line';
            $v['stack'] = 'total';
            $v['areaStyle'] = [
                'color' => $v['color']
            ];
            unset($v['color']);
            $c4s[] = $v;
        }
        $c4 = [
            'series' => $c4s,
            'title' => ts('SDJXSDB'),
            'legend' => [ts('distributor'), ts('dealer')],
            'xAxis' => $this->getMon1(),
        ];
        return $c4;
    }
    protected function chart3()
    {
        $year = request()->input('year') ?: date('Y');
        $d1 = DB::select("select date_format(installation_date,'%m') AS oaWonVEI, sum(length) as tX1xYm5Q from warranty as w left join warranty_to_vehicle_coverage as wvc on w.id = wvc.warranty_id WHERE installation_date like '".$year."%' AND w.user_id in (SELECT id FROM users WHERE user_type=2) group by oaWonVEI");
        $d2 = DB::select("select date_format(installation_date,'%m') AS oaWonVEI, sum(length) as tX1xYm5Q from warranty as w left join warranty_to_vehicle_coverage as wvc on w.id = wvc.warranty_id WHERE installation_date like '".$year."%' AND w.user_id in (SELECT id FROM users WHERE user_type=3) group by oaWonVEI");

        $c1 = $c2 = $this->getMon2();
        foreach ($d1 as $k => $v) {
            if (!isset($c1[$v->oaWonVEI])) {
                continue;
            }
            $c1[$v->oaWonVEI] = $v->tX1xYm5Q;
        }
        $c1_1 = [
            'name' => ts('distributor'),
            'type' => 'bar',
            //  'barWidth' => '20%',
            'color' => '#1891ff',
            'data' => array_values($c1),
        ];
        foreach ($d2 as $k => $v) {
            if (!isset($c2[$v->oaWonVEI])) {
                continue;
            }
            $c2[$v->oaWonVEI] = $v->tX1xYm5Q;
        }
        $c1_2 = [
            'name' => ts('dealer'),
            'type' => 'bar',
            // 'barWidth' => '20%',
            'color' => '#2fc25b',
            'data' => array_values($c2),
        ];

        $data = [
            'series' => [
                $c1_1,
                $c1_2
            ],
            'title' => ts('MXSTJ'),
            'legend' => [ts('distributor'), ts('dealer')],
            'xAxis' => $this->getMon1(),
        ];

        return $data;
    }

    protected function chart3Old()
    {
        $year = request()->input('year') ?: date('Y');
        $d1 = $d2 = [];
        $legend = $this->getMon2();
        foreach ($legend as $k => $k) {
            $d1[$k] = DB::table('warranty_to_vehicle_coverage')
                ->select(DB::raw('sum(length) as oaWonVEI'), DB::raw($k . ' as tX1xYm5Q'))
                ->whereIn('warranty_id', function ($query) use ($k, $year) {
                    $query->select('id')->from('warranty')
                        ->where(DB::raw("date_format(installation_date,'%m')"), $k)
                        ->where('installation_date', 'like', $year.'%')
                        ->whereIn('user_id', function ($sq) {
                            $sq->select('id')->from('users')
                                ->where('user_type', 2);
                        });
                });
            // ("select sum(length) as oaWonVEI from warranty_to_vehicle_coverage where warranty_id in (select id from warranty where date_format(installation_date,'%m') ='".$v."' and installation_date like '".$year."%' and user_id in (select id from users where user_type=2))");

            // $d1[$v] = DB::select("select sum(length) as oaWonVEI from warranty_to_vehicle_coverage where warranty_id in (select id from warranty where date_format(installation_date,'%m') ='".$v."' and installation_date like '".$year."%' and user_id in (select id from users where user_type=2))");
            // $d2[$v] = DB::select("select sum(length) as oaWonVEI from warranty_to_vehicle_coverage where warranty_id in (select id from warranty where date_format(installation_date,'%m') ='".$v."' and installation_date like '".$year."%' and user_id in (select id from users where user_type=2))");
        }
        $dg1 = '';
        foreach ($d1 as $k => $v) {
            if (empty($dg1)) {
                $dg1 = $v;
            } else {
                $dg1 = $dg1->union($v);
            }
        }
        // $dg1 = $d1['01']->unionAll(implode(',', $d1));

        return [$dg1->get()];
        /*$d1 = DB::select("SELECT date_format(installation_date,'%m') AS oaWonVEI,COUNT(id) AS tX1xYm5Q FROM warranty WHERE installation_date like '".$year."%' and user_id in (select id from users where user_type=2) GROUP BY oaWonVEI");*/
        /*$d2 = DB::select("SELECT date_format(installation_date,'%m') AS oaWonVEI,COUNT(id) AS tX1xYm5Q FROM warranty WHERE installation_date like '".$year."%' and user_id in (select id from users where user_type=3) GROUP BY oaWonVEI");*/


        return $d1;
    }

    protected function chart4($data)
    {
        $c6d = $data['series'];
        $c4s = [];
        foreach ($c6d as $k => $v) {
            $v['type'] = 'line';
            $v['stack'] = 'total';
            $v['areaStyle'] = [
                'color' => $v['color']
            ];
            unset($v['color']);
            $c4s[] = $v;
        }
        $c4 = [
            'series' => $c4s,
            'title' => ts('SDJXSDB'),
            'legend' => [ts('distributor'), ts('dealer')],
            'xAxis' => $this->getMon1(),
        ];
        return $c4;
    }

    protected function chart6()
    {
        $year = request()->input('year') ?: date('Y');
        $d1 = DB::select("SELECT date_format(installation_date,'%m') AS oaWonVEI,COUNT(id) AS tX1xYm5Q FROM warranty WHERE installation_date like '".$year."%' GROUP BY oaWonVEI");
        $d2 = DB::select("SELECT date_format(installation_date,'%m') AS oaWonVEI,COUNT(id) AS tX1xYm5Q FROM warranty WHERE installation_date like '".($year-1)."%' GROUP BY oaWonVEI");

        $c1 = $c2 = $this->getMon2();
        foreach ($d1 as $k => $v) {
            if (!isset($c1[$v->oaWonVEI])) {
                continue;
            }
            $c1[$v->oaWonVEI] = $v->tX1xYm5Q;
        }
        $c1_1 = [
            'name' => 'year_2020',
            'type' => 'bar',
            // 'barWidth' => '20%',
            'stack' =>  'total',
            'color' => '#1891ff',
            'data' => array_values($c1),
        ];
        foreach ($d2 as $k => $v) {
            if (!isset($c2[$v->oaWonVEI])) {
                continue;
            }
            $c2[$v->oaWonVEI] = $v->tX1xYm5Q;
        }
        $c1_2 = [
            'name' => 'year_2019',
            'type' => 'bar',
            // 'barWidth' => '20%',
            'stack' =>  'total',
            'color' => '#2fc25b',
            'data' => array_values($c2),
        ];

        $data = [
            'series' => [
                $c1_1,
                $c1_2
            ],
            'title' => ts('HBT'),
            'legend' => ['year_2020', 'year_2019'],
            'xAxis' => $this->getMon1(),
        ];
        return $data;
    }


    // 1-12月本地化
    protected function getMon1()
    {
        $c1_xAxis = [];
        foreach (range(1,12) as $v) {
            $c1_xAxis[] = ts('MON'.$v);
        }
        return $c1_xAxis;
    }
    // 补零后的1-12
    protected function getMon2()
    {
        $tm = [];
        foreach (range(1,12) as $v) {
            $k = str_pad($v, 2, 0, STR_PAD_LEFT);
            $tm[$k] = 0;
        }
        return $tm;
    }

    protected function parseWarrantyExcelFile($file)
    {
        $admin_id = session('admin.id');
        if (!file_exists($file)) {
            abort(404);
        }
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $first_name = $sheet->getCell('A4')->getValue();
        $region = $sheet->getCell('B4')->getValue(); // 获取id
        $province = $sheet->getCell('C4')->getValue(); // 获取id
        $city = $sheet->getCell('D4')->getValue(); // 获取id
        $zip = $sheet->getCell('E4')->getValue();
        $email_address = $sheet->getCell('F4')->getValue();
        $phone_number_2 = $sheet->getCell('G4')->getValue(); // 获取电话与extension 原文，-分割
        $address = $sheet->getCell('B5')->getValue();
        $year_id = $sheet->getCell('A8')->getValue();
        $make_name = $sheet->getCell('B8')->getValue(); // 获取make_id
        $model_id = $sheet->getCell('C8')->getValue(); // 使用原文输入

        $license_plate = $sheet->getCell('E8')->getValue(); // 车牌
        $vin_number = $sheet->getCell('F8')->getValue(); // 车架号

        $qudao = $sheet->getCell('A11')->getValue(); // 安装师傅
        $installer_name = $sheet->getCell('B11')->getValue(); // 安装师傅
        $installation_date = $sheet->getCell('C11')->getValue(); // 安装日期
        $installation_price = $sheet->getCell('E11')->getValue(); // 安装价格

        $installation_images_zip_url = $spreadsheet->getActiveSheet()->getCell('G11')->getHyperlink()->getUrl(); // 安装图片地址
        $installation_images = $this->getInstalationImagesByZipUrl($installation_images_zip_url);

        $msg = [];

        $qudao_id = getUserIdByName($qudao) ?: 0;
        if (empty($qudao_id)) {
            $msg[] = "门店或安装渠道不存在：" . $qudao;
        }

        $region_id = $this->getColumnIdByName('region_master', $region);
        if (empty($region_id)) {
            $msg[] = "地区不存在：" . $region;
        }
        $province_id = $this->getColumnIdByName('province_master', $province);
        if (empty($province_id)) {
            $msg[] = "省不存在：" . $province;
        }
        $city_id = $this->getColumnIdByName('cities', $city);
        if (empty($city_id)) {
            $msg[] = "城市不存在：" . $city;
        }
        $pns = explode('-', $phone_number_2);
        if (count($pns) != 2) {
            $msg[] = "电话号码有误，区号或电话号码不完整：" . $phone_number_2;
        }
        $make_id = getMakeId($make_name, $year_id);
        if (empty($make_id)) {
            $msg[] = "系统中不存在年份关联品牌：" . $make_id . ' / '.$year_id;
        }

        $vehicles = [];
        $row_no = 14;
        $max_row = 30;
        $n = '';
        while ($row_no<=$max_row && $n != '补充说明'){
            $item = [];
            $part = $sheet->getCell('A'.$row_no)->getValue(); // 套件
            $precut_or_manual = $sheet->getCell('B'.$row_no)->getValue(); // 裁切或手工
            $roll_number = $sheet->getCell('C'.$row_no)->getValue(); // 卷号
            $length = $sheet->getCell('E'.$row_no)->getValue(); // 使用长度
            $film_type_name = $sheet->getCell('F'.$row_no)->getValue(); // 膜的种类
            $width = $sheet->getCell('G'.$row_no)->getValue(); // 薄膜宽度
            $part = trim($part);
            if (empty($part) || $part == '补充说明') {
                $n = '补充说明';
            } else {
                $length_ = (int) $length;
                $width_ = (int) $width;
                $part_id = $this->getColumnIdByName('part_master', $part);
                if (empty($part_id)) {
                    $msg[] = "安装部位不存在：" . $part; // 第 {$row_no} 行
                }
                $rne = cRNe($roll_number);
                if (empty($rne)) {
                    $msg[] = "使用卷号不存在：" . $roll_number;
                }
                if (empty($length_)) {
                    $msg[] = "使用长度为0或非数字：" . $length;
                }
                $film_type_id = $this->getColumnIdByName('film_type_master', $film_type_name);
                if (empty($film_type_id)) {
                    $msg[] = "膜的种类不存在：" . $film_type_name;
                }

                $item = [
                    'precut_or_manual' => $precut_or_manual == '手工' ? 2 : 1,
                    'part_id' => $part_id,
                    'roll_number' => $roll_number,
                    'length' => $length_,
                    'width' => $width_,
                    'film_type_id' => $film_type_id,
                ];
                $vehicles[] = $item;
            }
            $row_no++;
        }
        $additional_comments = $sheet->getCell('A'.$row_no)->getValue();

        $user_id = session('admin.id');
        $model = new Warranty();
        $model->user_id = $qudao_id;
        $model->first_name = trim($first_name);
        $model->address = trim($address);
        $model->city = $city_id;
        $model->province_id = $province_id;
        $model->zip = trim($zip);
        $model->region_id = $region_id;
        $model->email_address = trim($email_address);
        $model->phone_number = trim(@$pns[1]);
        $model->extension = trim(@$pns[0]);
        $model->installer_name = trim($installer_name);
        $model->installation_date = trim($installation_date);
        $model->installation_price = trim($installation_price);

        $model->year_id = trim($year_id);
        $model->make_id = $make_id;
        $model->model_id = trim($model_id);
        $model->license_plate = trim($license_plate);
        $model->vin_number = trim($vin_number);
        $model->additional_comments = trim($additional_comments);

        $model->created_by = $admin_id;
        $model->warranty_id = makeWarrantyId($model->installation_date);
        $model->approved = 3;

        $model->save();

        $dtime = gDate();
        /*
         * 此处不再判断提交数据是否合法*/
        $uploads = [];
        foreach ($vehicles as $vk => $vv) {
            $vv['warranty_id'] = $model->id;
            $vv['created_at'] = $dtime;
            $vv['updated_at'] = $dtime;
            $uploads[] = $vv;
        }
        DB::table('warranty_to_vehicle_coverage')->insert($uploads);

        $imgs = [];
        $rmg = $installation_images;
        foreach ($rmg as $k => $v) {
            $item = [];
            $item['warranty_id'] = $model->id;
            $item['created_at'] = $dtime;
            $item['updated_at'] = $dtime;
            $item['image_src'] = $v;
            $item['part_id'] = $k+1;
            $imgs[] = $item;
        }
        if ($imgs) {
            DB::table('warranty_image')->insert($imgs);
        }
        $alogs = alogs('质保', '添加', $model->id, mmc($model, $uploads, $imgs), null);
        $msgModel = new WarrantyMsg();
        $msgModel->content = json_encode($msg);
        $msgModel->warranty_id = $model->id;
        $msgModel->status = 0;
        $msgModel->save();

        return $model;
    }

    // $installation_images
    protected function getInstalationImagesByZipUrl($url)
    {
        $filename = Str::random(16) . '.zip';
        $path = $this->getFileStorePath(2);
        $full_file_name = $path['base'] . $filename;
        $bool = file_put_contents($full_file_name, file_get_contents($url));
        $filenames = [];
        $info = mime_content_type($full_file_name);
        if ($info == 'application/zip') {
            $zip = zip_open($full_file_name);
            while ($zip_entry = zip_read($zip)) {
                if (zip_entry_open($zip, $zip_entry, "r")) {
                    $file_name = zip_entry_name($zip_entry);
                    $fnames = [];
                    foreach (range(0,11) as $kfn) {
                        $fnames[] = $kfn.'.jpg';
                    }
                    // ['0.jpg', '1.jpg', '2.jpg', '3.jpg', '4.jpg']
                    if (in_array($file_name, $fnames)) {
                        $content = zip_entry_read($zip_entry,zip_entry_filesize($zip_entry));
                        $pf = pathinfo($file_name);
                        $k = $pf['filename'];
                        $fileName = Str::random(32).'.jpg';
                        $temp = tmpfile();
                        fwrite($temp, $content);
                        // file_put_contents($temp, $content);
                        $temppath = stream_get_meta_data($temp)['uri'];
                        $ret = uploadFile($temppath, $fileName);
                        if ($ret['status'] == 'SUCCESS') {
                            $filenames[$k] = $ret['data']['url'];
                        }
                        fclose($temp);
                    }
                }
                zip_entry_close($zip_entry);
            }
        }
        return $filenames;
    }
    protected function getInstalationImagesByZip($filename)
    {

    }

    protected function getRollIdByNumber($number)
    {
        $data = DB::table('roll_master')->where('roll_number', $number)->first();
        return @$data->id ?: 0;
    }
    protected function getColumnIdByName($table, $name, $lang=0)
    {
        $table = trim($table);
        $name = trim($name);
        if (in_array($table, ['cities', 'film_type_master', 'make_master', 'part_master', 'product_types', 'province_master', 'region_master'])) {

        } else {
            return 0;
        }
        $data = DB::table($table)
            ->orWhere('english_value', $name)
            ->orWhere('traditional_chiness_value', $name)
            ->orWhere('simplified_chiness_value', $name)
            ->first();
        return @$data->id ?: 0;
    }

    protected function getRegionIdByName($name)
    {
        $data = DB::table('part_master')
            ->orWhere('english_value', $name)
            ->orWhere('traditional_chiness_value', $name)
            ->orWhere('simplified_chiness_value', $name)
            ->first();
        return @$data->id ?: 0;
    }
    protected function getPartIdByName($name)
    {
        $data = DB::table('part_master')
            ->orWhere('english_value', $name)
            ->orWhere('traditional_chiness_value', $name)
            ->orWhere('simplified_chiness_value', $name)
            ->first();
        return @$data->id ?: 0;
    }
    protected function getFiltypeIdByName($name)
    {
        $data = DB::table('film_type_master')
            ->orWhere('english_value', $name)
            ->orWhere('traditional_chiness_value', $name)
            ->orWhere('simplified_chiness_value', $name)
            ->first();
        return @$data->id ?: 0;
    }

    protected function getFileStorePath($type=1)
    {
        // 2 => 内存储，默认公共存储
        $path = '/upload/'.date('Ym').'/';
        if ($type==2){
            $basePath = storage_path('app'.$path);
        } else {
            $basePath = public_path($path);
        }
        if (!file_exists($basePath)) {
            mkdir($basePath, 0755);
        }
        return [
            $basePath, $path,
            'base' => $basePath,
            'path' => $path,
        ];
    }

    protected function getLimitOffset(Request $request)
    {
        $page = (int) $request->input('page') ?: 1;
        $limit = (int) $request->input('limit') ?: 15;
        $offset = ($page-1) * $limit;
        return [
            'limit' => $limit,
            'offset' => $offset,
            'page' => $page,
        ];
        return [$limit, $offset, $page];
    }

    protected function filterByColumns(Request $request, $model, $allow_column)
    {
        $value = trim($request->input('value'));
        if (empty($value)) {
            return $model;
        }
        $model->where(function ($query) use ($value, $allow_column) {
            foreach ($allow_column as $c) {
                $query->orWhere($c, 'like', '%'.$value.'%');
            }
        });
        return $model;
    }

    protected function filterByColumn(Request $request, $model, $allow_column)
    {
        $column = trim($request->input('label'));
        $value = trim($request->input('value'));
        if (empty($value)) {
            return $model;
        }
        if (in_array($column, $allow_column)) {
            $model = $model->where($column, 'like', '%'.$value.'%');
        }
        return $model;
    }

    /*protected function get*/
    protected function orderByColumn(Request $request, $model, $allow_column)
    {
        $column = trim($request->input('order_by_column'));
        if (in_array($column, $allow_column)) {
            $type = trim($request->input('order_by_method'));
            if ($type == 'desc') {
                $model = $model->orderByDesc($column);
            } else {
                $model = $model->orderBy($column);
            }
        } else {
            $model = $model->orderByDesc('id');
        }
        return $model;
    }

    /*
     * 膜卷excel导入
     * 膜卷批量导入*/
    public function rolls_import(Request $request)
    {
        checkAuthority();
        $page = [
            'page_name' => 'rolls',
            'title' => ts('bulkimport'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEROLL'),
                    'url' => '/admin/rolls'
                ]
            ],
        ];
        return view('admin.admin.rolls-import', ['page'=>$page]);

    }
    public function rolls_handle_import(Request $request)
    {
        checkAuthority();
        $page = [
            'page_name' => 'rolls',
            'title' => ts('bulkimport'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEROLL'),
                    'url' => '/admin/rolls'
                ]
            ],
        ];
        $user_id = session('admin.id');
        $file = $request->file('file');
        if (empty($file)) {
            return redirect()->back()->withInput()->with('trash', ['content'=>'文件不存在！', 'type'=>'error']);
        }
        if ($file->getClientOriginalExtension() != 'xlsx') {
            return redirect()->back()->withInput()->with('trash', ['content'=>'仅支持上传xlsx类型文件', 'type'=>'error']);
        }
        $filename = Str::random(16) . '.xlsx';
        $path = $this->getFileStorePath(2);
        $bool = $file->storeAs($path['path'], $filename, 'storage');
        $full_file_name = $path['path'].$filename; // /upload/XXX/filename
        $mod = new FileUpload();
        $mod->type = 2;
        $mod->name = '膜卷文件上传';
        $mod->user_id = $user_id;
        $mod->file = $full_file_name;
        $mod->save();

        $spreadsheet = IOFactory::load($path['base'] . $filename);
        $arrays = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $time = date('Y-m-d H:i:s');
        $datas = [];
        foreach ($arrays as $k => $v) {
            if ($k == 1) {
                continue;
            }
            $datas[] = [
                'roll_number' => trim($v['A']),
                'film_type_id' => (int) $v['B'],
                'width' => (int) $v['C'],
                'length' => (int) $v['D'],
                'created_at' => $time,
                'updated_at' => $time,
            ];
        }
        $roll_number = Arr::pluck($datas, 'roll_number');
        $ed = DB::table('roll_master')->whereIn('roll_number', $roll_number)->get(['roll_number']);
        $ex = Arr::pluck($ed, 'roll_number');
        $uploads = [];
        $restlen = [];
        foreach ($datas as $k => $v) {
            if (empty($v['roll_number'])) {
                continue;
            }
            if (in_array($v['roll_number'], $ex)) {
                $v['exist'] = true;
                $datas[$k] = $v;
            } else {
                $model = new RollMaster();
                $model->roll_number = $v['roll_number'];
                $model->width = $v['width'];
                $model->film_type_id = $v['film_type_id'];
                $model->length = $v['length'];
                $model->save();
                plusRestlen(1, $model->roll_number, $model->length);
            }
        }
        $alogs = alogs('膜卷', '导入', 0, $uploads, null);
        return view('admin.admin.rolls-import-result', [
            'data'=>$datas,
            'page' => $page,
        ]);
    }

    /*
     * 统一下载地址*/
    public function download(Request $request)
    {
        $a =  $request->input('a'); // rolls
        $b =  $request->input('b'); // template
        if ($b == 'template') {

            if ($a == 'rolls') {
                $file= storage_path('app/template/roll_template.xlsx');
                $filename = 'roll_template.xlsx';
            } elseif ($a == 'warranty') {
                $file= storage_path('app/template/warranty_template_2020.xlsx');
                $filename = 'warranty_template.xlsx';
            }
        }
        if (!empty($file) && !empty($filename)) {
            return response()->download($file, $filename);
        }
        return abort(404);
        /*$file= storage_path('app/template/warranty_template_2020.xlsx');
        $filename = 'warranty_template.xlsx';*/
        return response()->download($file, $filename);
    }
    /*
     * 省代业绩图表*/
    public function distributor_performance_charts(Request $request, $id)
    {
        $page = [
            'page_name' => 'distributor',
            'title' => ts('DISTRIBUTOR') . ts('performance'),
            'breadcrumb' => [
                [
                    'text' => ts('MANAGEDISTRIBUTOR'),
                    'url' => '/admin/distributor'
                ]
            ],
        ];
        // $id = $request->input('id');
        $type = $request->input('t');
        $admin_id = session()->get('admin.id');
        $admin_type = session()->get('admin.user_type');
        $year = $request->input('year') ?: date('Y');
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
            ->where('w.installation_date', 'like', $year.'%')
            ->whereIn('w.user_id', function ($query) use ($user) {
                $query->select('id')->from('users')
                    ->where('id', $user->id)
                    ->orWhere('creator_id', $user->id);
            });
        $model->select([DB::raw("u.abbr AS oaWonVEI"), DB::raw('sum(wvc.length) as tX1xYm5Q')])
            ->groupBy("w.user_id");
        $data = $model->get();
        $chart1 = [];
        foreach ($data as $k => $v) {
            $chart1[$v->oaWonVEI] = $v->tX1xYm5Q;
        }
        arsort ($chart1);
        return view('admin.admin.performance-charts', [
            'page' => $page,
            'charts' => [
                'c1' => [
                    'title' => ts('CPXL'),
                    'yAxis' => array_keys($chart1),
                    'series' => array_values($chart1),
                ]
            ],
            'table' => [
                't1' => $chart1,
            ]
        ]);
    }

}
