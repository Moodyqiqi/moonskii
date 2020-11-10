<?php
namespace App\Http\Controllers\Account\Member;

use App\Http\Controllers\Controller;
use App\Http\Controllers\OfficeWork;
use App\Model\Account;
use App\Model\MemberDanwei;
use App\Model\MemberLztxth as Model;
use App\Model\MemberLztxthItem as Model2;
use App\Model\Ssjt;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LztxthController extends Controller
{
    protected $suffix = '_附属单位';
    protected $page_title = '廉政谈心谈话';
    protected $page_name = 'member/lztxth';
    protected $view_index = 'account.member.lztxth.index';
    protected $view_show = 'account.member.lztxth.show';
    protected $view_edit = 'account.member.lztxth.edit';
    protected $view_create = 'account.member.lztxth.create';
    protected $view_baobiao = 'account.member.lztxth.baobiao';
    protected $view_detail = 'account.member.lztxth.detail';
    protected $table = 'abel_chen_member_lztxth';


    public function danwei()
    {
        return MemberDanwei::get(['id', 'name']);
    }
    public function yuefen()
    {
        $d = [];
        foreach (range(2020, date('Y')) as $y) {
            foreach (range(1,12) as $M) {
                $m = str_pad($M, 2, 0, 0);
                $d[] = $y.$m;
            }
        }
        return $d;
    }

    public function jidu()
    {
        $d = [];
        foreach (range(2020, date('Y')) as $y) {
            foreach (range(1,4) as $j) {
                $d[] = $y.'-'.$j;
            }
        }
        return $d;
    }

    public function index(Request $request)
    {
        $page = [
            'page_name' => $this->page_name,
            'title' => $this->page_title . $this->suffix
        ];

        $mod = new Model();
        $mod = $mod->withTrashed();

        $yuefen = trim($request->input('yuefen'));
        $nianfen = (int) trim($request->input('nianfen'));
        $jidu = trim($request->input('jidu'));
        if (!empty($yuefen)) {
            $mod = $mod->where('c1', $yuefen);
        } elseif (!empty($jidu)) {
            $jd = explode('-', $jidu);
            $m = (int) @$jd[1];
            $m = $m * 3;
            $y = (int) @$jd[0];
            $m = str_pad($m, 2, 0, 0);
            $eT = $y.$m;
            $bm = str_pad(($m-2), 2, 0, 0);
            $bT = $y.$bm;
            $mod = $mod->where([
                ['c1', '>=', $bT],
                ['c1', '<=', $eT],
            ]);
        } elseif (!empty($nianfen)) {
            $bT = $nianfen.'01';
            $eT = $nianfen.'12';
            $mod = $mod->where([
                ['c1', '>=', $bT],
                ['c1', '<=', $eT],
            ]);
        }
        $danwei_id = trim($request->input('danwei'));
        if (!empty($danwei_id)) {
            $mod = $mod->where('danwei_id', $danwei_id);
        }

        if ($request->input('trashed') == 1) {
            $mod = $mod->onlyTrashed();
        }
        $name = trim($request->input('name'));
        if (!empty($name)) {
            $mod = $mod->where('c1', 'like', '%'.$name.'%');
        }

        $data = $mod->paginate(25);


        $filter = [
            'danwei' => $this->danwei(),
            'yuefen' => $this->yuefen(),
            'jidu' => $this->jidu(),
        ];

        return view($this->view_index, [
            'page' => $page,
            'data' => $data,
            'filter'=>$filter
        ]);
    }

    public function show(Request $request, $id)
    {
        $model = Model::findOrFail($id);
        $page = [
            'page_name' => $this->page_name,
            'title' => '查看' . $this->page_title . $this->suffix
        ];
        // $data = $model->items()->get();
        // ->withTrashed()
        $data = $model->items()->paginate(25);
        // return [$data];
        return view($this->view_show, [
            'page' => $page,
            'data' => $data,
        ]);
    }

    public function edit(Request $request, $id)
    {
        $model = Model::findOrFail($id);
        $data = $model->toArray();
        $page = [
            'page_name' => $this->page_name,
            'title' => '编辑' . $this->page_title . $this->suffix
        ];
        $form = $this->form($data);

        return view($this->view_edit, [
            'page' => $page,
            'data'=>$model,
            'form' => $form
        ]);
    }
    public function update(Request $request, $id)
    {
        $model = Model::findOrFail($id);
        $data = $this->checkInput($request);
        foreach ($data as $k => $v) {
            $model->$k = $v;
        }
        $model->account_id = $model->account_id ?: session('account.id');
        $model->riqi = @strtotime($data['riqi']);
        $model->save();
        return redirect('/account/'.$this->page_name);
    }

    public function delete(Request $request, $id)
    {
        $model = Model::findOrFail($id);
        $model->delete();
        return ['status'=>'SUCCESS'];
    }

    public function restore(Request $request, $id)
    {
        $model = Model::withTrashed()->findOrFail($id);
        $model->restore();
        return ['status'=>'SUCCESS'];
    }

    protected function ssjt()
    {
        $data = Ssjt::get(['id', 'name']);
        return $data;
    }

    public function create(Request $request)
    {
        $page = [
            'page_name' => $this->page_name,
            'title' => '新增' . $this->page_title
        ];
        $data = [];
        $form = $this->form($data);
        return view($this->view_create, [
            'page' => $page,
            'data'=>$data,
            'form' => $form
        ]);
    }

    public function store(Request $request)
    {
        $model = new Model();
        $data = $this->checkInput($request);
        foreach ($data as $k => $v) {
            $model->$k = $v;
        }
        $model->account_id = session('account.id');
        $model->riqi = @strtotime($data['riqi']);
        $model->save();
        return redirect('/account/'.$this->page_name);
    }

    protected function form($data=[])
    {
        $total = 0;
        if (!empty($data->items()->count())) {
            $total = $data->items()->count();
        }
        $total = @$data->items()->count();
        return $form = [
            [
                'key' => 'danwei_name',
                'label' => '单位',
                'value' => @$data['danwei_name'] ?: @$data->danwei_name,
            ],
            [
                'key' => 'c1',
                'label' => '月份',
                'value' => @$data['c1'] ?: @$data->c1,
            ],
            [
                'key' => 'c2',
                'label' => '提交总数',
                'value' => $total,
            ],
        ];
    }

    public function checkInput(Request $request)
    {
        $ks = ['name','qqdw','dwzw','ssjt_id','riqi','qkms','cljg'];
        $form = $this->form();
        $data = [];
        foreach ($form as $item) {
            $v = $request->input($item['key']);
            $v = trim($v);
            if (empty($v)) {
                return back()->withInput()->with('trash', $item['label'].' 不能为空');
            }
            $data[$item['key']] = $v;
        }
        return $data;
    }

    protected function search(Request $request)
    {
        $mod = new Model();
        $yuefen = trim($request->input('yuefen'));
        $nianfen = (int) trim($request->input('nianfen'));
        $jidu = trim($request->input('jidu'));
        if (!empty($yuefen)) {
            $mod = $mod->where('c1', $yuefen);
        } elseif (!empty($jidu)) {
            $jd = explode('-', $jidu);
            $m = (int) @$jd[1];
            $m = $m * 3;
            $y = (int) @$jd[0];
            $m = str_pad($m, 2, 0, 0);
            $eT = $y.$m;
            $bm = str_pad(($m-2), 2, 0, 0);
            $bT = $y.$bm;
            $mod = $mod->where([
                ['c1', '>=', $bT],
                ['c1', '<=', $eT],
            ]);
        } elseif (!empty($nianfen)) {
            $bT = $nianfen.'01';
            $eT = $nianfen.'12';
            $mod = $mod->where([
                ['c1', '>=', $bT],
                ['c1', '<=', $eT],
            ]);
        }
        $danwei_id = trim($request->input('danwei'));
        if (!empty($danwei_id)) {
            $mod = $mod->where('danwei_id', $danwei_id);
        }
        return $mod;
        /*$data = $mod->get();
        return $data;*/
    }

    // 假如ID设置，则来自与详情页，否则来自于列表页
    // c=1 total, c=2 detail，c=3 search+total, c=4 search+detail
    public function download(Request $request, $id=null)
    {
        $type = $request->input('type');
        $c = (int) $request->input('c');
        $work = new OfficeWork();
        $res = [];
        $data = [];
        if (isset($id)) {
            $c = 2;
            $detail_id = $request->input('detail_id');
            if (empty($detail_id)) {
                $data = Model2::where('lztxth_id', $id)->get();
                // $data = $mod->items()->get();
            } else {
                $mod = Model2::findOrFail($id);
                $data = [$mod];
            }
        } else {
            if ($c==1) {
                $data = Model::limit(2000)->get();
            } elseif ($c==2) {
                $data = Model2::limit(2000)->get();
            } elseif ($c==3) {
                $model = $this->search($request);
                $data = $model->get();
            } elseif ($c==4) {
                $model = $this->search($request);
                $p = $model->get();
                $ids = Arr::pluck($p, 'id');
                $data = Model2::whereIn('lztxth_id', $ids)->get();
            } else {
                // 默认为1
                $data = Model::limit(2000)->get();
            }

        }
        if ($c==1 || $c==3) {
            foreach ($data as $k => $v) {
                $res[] = $this->form($v);
            }
        } else {
            foreach ($data as $k => $v) {
                $res[] = $this->detailForm($v);
            }
        }
        return $work->makeExcel($res);
        return $data;
    }

    public function baobiao(Request $request)
    {
        $page = [
            'page_name' => $this->page_name,
            'title' => '新增' . $this->page_title
        ];
        $data = [];
        $chart = [];
        $year = (int) $request->input('year') ?: date('Y');
        // $btime = mktime(0,0,0,1,1, $year);
        // $etime = mktime(23,59,59,12,31, $year);
        $btime = $year.'01';
        $etime = $year.'12';
        $label = [];
        foreach (range(1,12) as $v) {
            $l1 = $year.str_pad($v, 2, 0, STR_PAD_LEFT);
            $l2 = $year.'-'.str_pad($v, 2, 0, STR_PAD_LEFT);
            $label[$l1] = $l2;
        }
        // 按照月份获取数据，月份存储规则是Ym，如202002
        // 如过是Ymd，如20200201，用div取整数商
        $sql1 = "SELECT count(id) as oaW, c1 as onV  FROM $this->table WHERE c1 >= $btime AND c1<=$etime GROUP BY onV";
        $c1 = DB::select($sql1);
        // 按照单位获取数据，单位存储为单位ID，如1，再根据单位数据获取单位名称
        $sql2 = "SELECT count(id) as oaW, danwei_id as onV  FROM $this->table WHERE c1 >= $btime AND c1<=$etime GROUP BY onV";
        $c2 = DB::select($sql2);
        $danwei = MemberDanwei::all(['id', 'name']);
        $dw = [];
        foreach ($danwei as $d) {
            $dw[$d->id] = $d->name;
        }


        return view($this->view_baobiao, [
            'page' => $page,
            'data'=>$data,
            'chart' => [
                'c1' => [
                    'title' => '每月投稿数',
                    'data' => $this->cleanData2($c1, $label, 2)
                ],
                'c2' => [
                    'title' => '各单位投稿数',
                    'data' => $this->cleanData2($c2, $dw, 2),
                ],
            ]
        ]);
    }

    /*
     * $legand_type默认为1，$legand=['2020-01'……]
     * $legand_type=2，$legand=['202001'=》'2020-01'……]
     * $legand_type=3，$legand=['2020-01'=》'202001'……]
     * 返回值为['label'=>'2020-01','value'=>value]
     * */
    protected function cleanData2($data, $legand, $legand_type=1)
    {
        $r1 = [];
        $res = [];
        foreach ($data as $k => $v) {
            $r1[$v->onV] = $v->oaW;
        }
        foreach ($legand as $k => $v) {
            if ($legand_type==2) {
                $res[] = [
                    'label' => $v,
                    'value' => @$r1[$k]
                ];
                // $res[$v] = @$r1[$k] ?: 0;
            } elseif ($legand_type==3) {
                # $res[$k] = @$r1[$v] ?: 0;
                $res[] = [
                    'label' => $k,
                    'value' => @$r1[$v]
                ];
            } else {
                $res[] = [
                    'label' => $v,
                    'value' => @$r1[$v]
                ];
                # $res[$v] = @$r1[$v] ?: 0;
            }
        }
        return $res;
    }

    protected function cleanData1($data)
    {
        // oaW, onV
        $res = [];
        foreach ($data as $k => $v) {
            $res[] = [
                'label' => $v->onV,
                'value' => $v->oaW,
            ];
        }
        return $res;
    }

    public function test1()
    {

        $d = scandir(public_path('uploads/202007'));
        foreach (range(1,500) as $k => $v) {
            $no = array_rand($d, rand(2,10));
            $val = [];
            foreach ($no as $k => $v) {
                if ($v <= 2) {
                    continue;
                }
                $val[] = $d[$v];
            }

            $mod = new Model;
            $mod->member_id = rand(4, 32);
            if ($mod->member_id <=3) {
                $mod->member_id = 1;
                $dw = 1;
            } else {
                $dw = $mod->member_id - 3;
            }
            $mod->danwei_id = $dw;
            $str = time() + rand(1,86400) * rand(1,80) - rand(1,86400) * rand(1,480);
            $str = $str >= time() ? time() : $str;
            $mod->c1 = date('Ym', $str);
            $mod->c3 = '测试测试内容' . $k . rand(1,86400);
            $mod->c4 = Str::random(6);
            $ki = rand(6,8);
            $mod->c6 = '';
            $mod->c7 = '';
            $mod->c8 = '';
            $ki = 'c'.$ki;
            if (!empty($val)) {
                $mod->$ki = implode(',', $val);
            }
            $luyong = [];
            foreach (range(1, rand(2, 5)) as $k =>$v) {
                if ($v >= 3) {
                    $pt = rand(1,8);
                    $luyong[$pt] = rand(1, 86400);
                }
            };
            $mod->save();
            $mod->luyongs()->sync($luyong);
        }
    }

    protected function detailForm($data)
    {
        return $form = [
            [
                'key' => 'danwei_id',
                'label' => '提交单位',
                'value' => @$data['danwei_name'],
            ],
            [
                'key' => 'c1',
                'label' => '谈话时间',
                'value' => date('Y-m-d', strtotime($data['c1'])),
            ],
            [
                'key' => 'c2',
                'label' => '谈话地点',
                'value' => @$data['c2'],
            ],
            [
                'key' => 'c3',
                'label' => '谈话人',
                'value' => @$data['c3'],
            ],
            [
                'key' => 'c4',
                'label' => '单位及职务',
                'value' => @$data['c4'],
            ],
            [
                'key' => 'c5',
                'label' => '谈话对象',
                'value' => @$data['c5'],
            ],
            [
                'key' => 'c6',
                'label' => '单位及职务（岗位）',
                'value' => @$data['c6'],
            ],
            [
                'key' => 'c7',
                'label' => '谈话形式,个人/集体',
                'value' => @$data['c7'],
                'type' => 'text',
            ],
            [
                'key' => 'c8',
                'label' => '谈话要点',
                'value' => @$data['c8'],
            ],
            [
                'key' => 'c9',
                'label' => '记录人',
                'value' => @$data['c9'],
            ],
            [
                'key' => 'c10',
                'label' => '备注',
                'value' => @$data['c10'],
            ],
        ];

    }

    public function detail(Request $request, $id)
    {
        $model = Model2::findOrFail($id);
        $data = $model->toArray();
        $page = [
            'page_name' => $this->page_name,
            'title' => '查看' . $this->page_title . $this->suffix
        ];
        // $form = $this->detailForm($data);

        return view($this->view_detail, [
            'page' => $page,
            'data'=>$model,
            // 'form' => $form
        ]);

        return $model;
    }

}

