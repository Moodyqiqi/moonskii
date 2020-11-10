<?php
namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Controllers\OfficeWork;
use App\Model\Account;
use App\Model\ChenStatistic as Model;
use App\Model\MemberDanwei;
use App\Model\Ssjt;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StatisticsController extends Controller
{
    protected $suffix = '';
    protected $page_title = '信访台账统计';
    protected $page_name = 'statistics';
    protected $view_index = 'account.statistics.index';
    protected $view_show = 'account.statistics.show';
    protected $view_edit = 'account.statistics.edit';
    protected $view_create = 'account.statistics.create';
    protected $view_baobiao = 'account.statistics.baobiao';
    protected $table = 'abel_chen_statistic';

    /*
     * 不同年份使用不同的数据表
     * 使用不同的样式
     * */


    public function index(Request $request)
    {
        $page = [
            'page_name' => $this->page_name,
            'title' => $this->page_title . $this->suffix
        ];

        $mod = new Model();
        // $mod = $mod->withTrashed();
        if ($request->input('trashed') == 1) {
            $mod = $mod->onlyTrashed();
        }
        $name = trim($request->input('name'));
        if (!empty($name)) {
            $mod = $mod->where('c1', 'like', '%'.$name.'%');
        }
        $data = $mod->paginate(25);
        return view($this->view_index, [
            'page' => $page,
            'data' => $data,
        ]);
    }

    public function show(Request $request, $id)
    {
        $model = Model::findOrFail($id);
        $page = [
            'page_name' => $this->page_name,
            'title' => '查看' . $this->page_title
        ];
        $data = $this->form($model->toArray());
        return view($this->view_show, [
            'page' => $page,
            'data'=>$data,
        ]);
    }

    public function edit(Request $request, $id)
    {
        $model = Model::findOrFail($id);
        $data = $model->toArray();
        $page = [
            'page_name' => $this->page_name,
            'title' => '编辑' . $this->page_title
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

    protected function showForm()
    {}

    protected function form($data=[])
    {
        if (!is_array($data)) {
            $data  =@$data->toArray();
        }
        return $form = [
            ['label'=>"编号",'key'=>'c1','value'=>@$data['c1']],
            ['label'=>"中交案管系统编号",'key'=>'c2','value'=>@$data['c2'],'required'=>'no'],
            ['label'=>"受理时间",'key'=>'c3','value'=>@$data['c3']],
            ['label'=>"被举报人",'key'=>'c4','value'=>@$data['c4']],
            ['label'=>"单位及职务",'key'=>'c5','value'=>@$data['c5'],'required'=>'no'],
            ['label'=>"所属中心/集团",'key'=>'c6','value'=>@$data['c6'],'required'=>'no'],
            ['label'=>"信访来源",'key'=>'c7','value'=>@$data['c7'],'required'=>'no'],
            ['label'=>"信访形式",'key'=>'c8','value'=>@$data['c8'],'required'=>'no'],
            ['label'=>"如为转办，转办编号",'key'=>'c9','value'=>@$data['c9'],'required'=>'no'],
            ['label'=>"信访方式",'key'=>'c10','value'=>@$data['c10'],'required'=>'no'],
            ['label'=>"举报人姓名",'key'=>'c11','value'=>@$data['c11'],'required'=>'no'],
            ['label'=>"举报内容",'key'=>'c12','value'=>@$data['c12'],'required'=>'no'],
            ['label'=>"办理方式",'key'=>'c13','value'=>@$data['c13'],'required'=>'no'],
            ['label'=>"转办至下级编号",'key'=>'c14','value'=>@$data['c14'],'required'=>'no'],
            ['label'=>"转办至下级时间",'key'=>'c15','value'=>@$data['c15'],'required'=>'no'],
            ['label'=>"查处状态",'key'=>'c16','value'=>@$data['c16'],'required'=>'no'],
            ['label'=>"处置方式",'key'=>'c17','value'=>@$data['c17'],'required'=>'no'],
            ['label'=>"处理结果",'key'=>'c18','value'=>@$data['c18'],'required'=>'no'],
            ['label'=>"处理情况(简要)",'key'=>'c19','value'=>@$data['c19'],'required'=>'no'],
            ['label'=>"后续处理",'key'=>'c20','value'=>@$data['c20'],'required'=>'no'],
            ['label'=>"“四种形态”运用情况",'key'=>'c21','value'=>@$data['c21'],'required'=>'no'],
            ['label'=>"承办人",'key'=>'c22','value'=>@$data['c22'],'required'=>'no'],
            ['label'=>"备注",'key'=>'c23','value'=>@$data['c23'],'required'=>'no'],
            ['label'=>"案卷（原件）何处",'key'=>'c24','value'=>@$data['c24'],'required'=>'no'],
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
            if (@$item['required'] != 'no' && empty($v)) {
                return back()->withInput()->with('trash', $item['label'].' 不能为空');
            }
            $data[$item['key']] = $v;
        }
        return $data;
    }

    public function download(Request $request, $id=null)
    {
        // 假如ID设置，则来自与详情页，否则来自于列表页
        $type = $request->input('type');
        $work = new OfficeWork();
        if (isset($id)) {
            $mod = Model::findOrFail($id);
            $data = [$mod];
            if ($type == 'word') {
                $data = $this->form($mod);
                return $work->makeDocx($data);
            }
            // if ($type)
        } else {
            $data = Model::limit(2000)->get();
        }
        $res = [];
        foreach ($data as $k => $v) {
            $res[] = $this->form($v);
        }
        return $work->makeExcel($res);
        return $data;
    }

    public function baobiao(Request $request)
    {
        $page = [
            'page_name' => $this->page_name,
            'title' => '报表' . $this->page_title
        ];
        $data = [];
        $chart = [];
        // date('Y')
        $year = (int) $request->input('year') ?: 2019;
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
        // date_format 2019-01-01
        $table =  $this->table;
        // $sql1 = "SELECT count(id) as oaW, DATE_FORMAT(c3, '%Y%m') as onV  FROM $table WHERE c3 >= $btime AND c3<=$etime GROUP BY onV";
        $sql1 = "SELECT count(id) as oaW, DATE_FORMAT(c3, '%Y%m') as onV  FROM $table WHERE c3 like '$year%' GROUP BY onV";
        $c1 = DB::select($sql1);
        // 按照单位名称分组，可能有偏差
        $sql2 = "SELECT count(id) as oaW, c6 as onV  FROM $table WHERE c3 like '$year%' GROUP BY onV";
        $c2 = DB::select($sql2);

        // 处置方式
        $sql17 = "SELECT count(id) as oaW, c17 as onV  FROM $table WHERE c3 like '$year%' GROUP BY onV";
        $c17 = DB::select($sql17);
        // 处理结果
        $sql18 = "SELECT count(id) as oaW, c18 as onV  FROM $table WHERE c3 like '$year%' GROUP BY onV";
        $c18 = DB::select($sql18);
        // “四种形态”运用情况
        $sql21 = "SELECT count(id) as oaW, c21 as onV  FROM $table WHERE c3 like '$year%' GROUP BY onV";
        $c21 = DB::select($sql21);
        // 承办人
        $sql22 = "SELECT count(id) as oaW, c22 as onV  FROM $table WHERE c3 like '$year%' GROUP BY onV";
        $c22 = DB::select($sql22);

        /*$danwei = MemberDanwei::all(['id', 'name']);
        $dw = [];
        foreach ($danwei as $d) {
            $dw[$d->id] = $d->name;
        }*/
        $data22 = [];
        $d22 = [];
        foreach ($c22 as $k => $v) {
            $l = trim($v->onV);
            $s = explode('、', $l);
            foreach ($s as $w) {
                $d22[] = [
                    'oaW' => $v->oaW,
                    'onV' => $w,
                ];
            }
        }
        $data['year'] = $year;
        $data['total'] = array_sum(Arr::pluck($c1, 'oaW'));

        return view($this->view_baobiao, [
            'page' => $page,
            'data'=>$data,
            'chart' => [
                'c1' => [
                    'title' => '每月信访数',
                    'data' => $this->cleanData2($c1, $label, 2)
                ],
                'c2' => [
                    'title' => '各单位信访数',
                    'data' => $this->cleanData1($c2, 2),
                ],
                'c17' => [
                    'title' => '处置方式',
                    'data' => $this->cleanData1($c17)
                ],
                'c18' => [
                    'title' => '处理结果',
                    'data' => $this->cleanData1($c18)
                ],
                'c21' => [
                    'title' => '“四种形态”运用情况',
                    'data' => $this->cleanData1($c21)
                ],
                'c22' => [
                    'title' => '承办人',
                    'data' => $this->cleanData1($d22)
                ],
            ]
        ]);

    }

    // 2019年使用数据
    protected function y2019()
    {
        // 信访来源
        $c7 = [
            '自收',
            '领导批办',
            '国资委纪委转来',
            '中纪委转来',
            '国家信访局转来',
            '各省地市纪检部门',
            '其他部门转来',
        ];
        // 信访形式
        $c8 = [
            '来信',
            '来访',
            '来电',
            '网络举报',
            '其他方式',
        ];
        // 信访方式
        $c10 = [
            '匿名',
            '署名',
            '联名访',
            '集体访',
        ];
        // 办理方式
        $c13 = [
            1 => '自办',
            2 => '转办',
            3 => '退回'
        ];
        // 查处状态
        $c16 = [
            1 => '未办理',
            2 => '正在办理',
            3 => '已办结'
        ];
        // 处置方式
        $c17 = [
            1 => '初核',
            2 => '函询',
            3 => '暂存',
            4 => '了结',
            5 => '拟立案',
        ];
        // 处理结果
        $c18 = [
            1 => '结案',
            2 => '了结',
        ];
        // “四种形态”运用情况
        $c21 = [
            1 => '红红脸、出出汗',
            2 => '党纪轻处分、组织调整',
            3 => '党纪重处分、重大职务调整',
            4 => '严重违纪涉嫌违法立案审查'
        ];
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
                    'label' => $k ?: '-',
                    'value' => @$r1[$v]
                ];
            } else {
                $res[] = [
                    'label' => $v ?: '-',
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
            if (is_array($v)) {
                $res[] = [
                    'label' => $v['onV'] ?: '-',
                    'value' => $v['oaW'],
                ];
            } else {
                $res[] = [
                    'label' => $v->onV ?: '-',
                    'value' => $v->oaW,
                ];
            }
        }
        return $res;
    }

}

