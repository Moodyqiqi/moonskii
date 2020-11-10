<?php
namespace App\Http\Controllers\Account\Member;

use App\Http\Controllers\Controller;
use App\Model\Account;
use App\Model\MemberDanwei;
use App\Model\MemberGaojian as Model;
use App\Model\Ssjt;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GaojianController extends Controller
{
    protected $suffix = '_附属单位';
    protected $page_title = '新闻稿件';
    protected $page_name = 'member/gaojian';
    protected $view_index = 'account.member.gaojian.index';
    protected $view_show = 'account.member.gaojian.show';
    protected $view_edit = 'account.member.gaojian.edit';
    protected $view_create = 'account.member.gaojian.create';
    protected $view_baobiao = 'account.member.gaojian.baobiao';
    protected $table = 'abel_chen_member_gaojian';

    public function index(Request $request)
    {
        $page = [
            'page_name' => $this->page_name,
            'title' => $this->page_title . $this->suffix
        ];

        $mod = new Model();
        $mod = $mod->withTrashed();
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
        return view($this->view_show, [
            'page' => $page,
            'data'=>$model,
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

    protected function form($data=[])
    {
        $ssjt = $this->ssjt();
        return $form = [
            [
                'key' => 'name',
                'label' => '被鉴定人',
                'value' => @$data['name'],
            ],
            [
                'key' => 'qqdw',
                'label' => '请求单位',
                'value' => @$data['qqdw'],
            ],
            [
                'key' => 'dwzw',
                'label' => '被鉴定人单位职务',
                'value' => @$data['dwzw'],
            ],
            [
                'key' => 'ssjt_id',
                'label' => '被鉴定人所属中心或集团',
                'value' => @$data['ssjt_id'],
                'type' => 'select',
                'options' => $ssjt,
                'dict' => [
                    'label' => 'name',
                    'value' => 'id'
                ]
            ],
            [
                'key' => 'riqi',
                'label' => '日期',
                'value' => @$data['riqi'],
                'type' => 'date',
                'max' => date('Y-m-d')
            ],
            [
                'key' => 'qkms',
                'label' => '情况描述',
                'value' => @$data['qkms'],
                'type' => 'text',
            ],
            [
                'key' => 'cljg',
                'label' => '处理结果',
                'value' => @$data['cljg'],
                'type' => 'text',
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

    public function download(Request $request, $id=null)
    {
        $model = Model::findOrFail($id);
        $c6 = explode(',', $model->c6);
        $c7 = explode(',', $model->c7);
        $c8 = explode(',', $model->c8);

        $file = [];

        $d = new \ZipArchive;
        $count = 0;
        $zip_name = time().'.zip';
        $temp_file = tempnam(sys_get_temp_dir(), 'ZipArchive');
        $d->open($temp_file, \ZipArchive::CREATE);

        // $d->addFromString('readme.txt', "# abel\r\nabel");
        $note = [];
        foreach ($c6 as $k => $v) {
            if (empty($v)) {
                continue;
            }
            $url = parse_url($v);
            if (empty($url['host'])) {
                $con = @file_get_contents(public_path($v));
            } else {
                $con = @file_get_contents($v);
            }
            $ext = '';
            $ext_info = pathinfo($v);
            if (!empty($ext_info['extension'])) {
                $ext = '.'.$ext_info['extension'];
            }
            if (!empty($con)) {
                $d->addFromString('wen_'.($k+1).$ext, $con);
                $note[] = '文件'.($k+1).'下载成功；';
            } else {
                $note[] = '文件'.($k+1).'下载失败；';
            }
            $count++;
        }
        foreach ($c7 as $k => $v) {
            if (empty($v)) {
                continue;
            }
            $url = parse_url($v);
            if (empty($url['host'])) {
                $con = @file_get_contents(public_path($v));
            } else {
                $con = @file_get_contents($v);
            }
            $ext = '';
            $ext_info = pathinfo($v);
            if (!empty($ext_info['extension'])) {
                $ext = '.'.$ext_info['extension'];
            }
            if (!empty($con)) {
                $d->addFromString('tu_'.($k+1).$ext, $con);
                $note[] = '图片'.($k+1).'下载成功；';
            } else {
                $note[] = '图片'.($k+1).'下载失败；';
            }
            $count++;
        }
        foreach ($c8 as $k => $v) {
            if (empty($v)) {
                continue;
            }
            $url = parse_url($v);
            if (empty($url['host'])) {
                $con = @file_get_contents(public_path($v));
            } else {
                $con = @file_get_contents($v);
            }
            $ext = '';
            $ext_info = pathinfo($v);
            if (!empty($ext_info['extension'])) {
                $ext = '.'.$ext_info['extension'];
            }
            if (!empty($con)) {
                $d->addFromString('shi_'.($k+1).$ext, $con);
                $note[] = '视频'.($k+1).'下载成功；';
            } else {
                $note[] = '视频'.($k+1).'下载失败；';
            }
            $count++;
        }

        $d->addFromString('readme.txt', "# abel\r\n".implode("\r\n", $note));

        $d->close();
        return response()->download($temp_file, $zip_name);
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

}

