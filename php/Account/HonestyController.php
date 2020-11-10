<?php
namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Model\Account;
use App\Model\ChenHonesty as Model;
use App\Model\ChenHonesty2020A1;
use App\Model\ChenHonesty2020A2;
use App\Model\ChenHonesty;
use App\Model\Ssjt;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class HonestyController extends Controller
{
    protected $suffix = '';
    protected $page_title = '廉洁从业鉴定';
    protected $page_name = 'honesty';
    protected $view_index = 'account.honesty.index';
    protected $view_show = 'account.honesty.show';
    protected $view_edit = 'account.honesty.edit';
    protected $view_create = 'account.honesty.create';

    public function index(Request $request)
    {

        $page = [
            'page_name' => $this->page_name,
            'title' => $this->page_title . $this->suffix
        ];

        $year = $request->input('year');
        $type = $request->input('type');
        if ($year == 2020) {
            if ($type == 2) {
                $model = ChenHonesty2020A2::orderBy('id');
            } else {
                $model = ChenHonesty2020A1::orderBy('id');
            }

        } else {
            $model = new Model();
            // $mod = $mod->withTrashed();
            if ($request->input('trashed') == 1) {
                $model = $model->onlyTrashed();
            }
            $name = trim($request->input('name'));
            if (!empty($name)) {
                $model = $model->where('c1', 'like', '%'.$name.'%');
            }
        }
        $data = $model->get();
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

    public function import(Request $request)
    {
        $page = [
            'page_name' => $this->page_name,
            'title' => '批量上传',
            'breadcrumb' => [
                [
                    'text' => '廉洁从业鉴定',
                    'url' => '/account/honesty'
                ]
            ],
        ];
        return view('account.honesty.import', [
            'page' => $page,
        ]);

    }
    public function handle_import(Request $request)
    {
        $year = $request->input('year');
        $type = $request->input('type');
        if (empty($year) || empty($type)) {
            return back()->withInput()->with('trash', [ 'content' => '请选择年份及类型！', 'type' => 'error']);
        }
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
        $file = $file->storeAs(date('Ym'), Str::random(40) . '.' .$file->getClientOriginalExtension(), 'pri');
        $file = storage_path('app/files/'.$file);

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $row_no = 1;
        $n = 'x';
        $data = [];
        while ($n){
            $item = [];
            $n = trim($sheet->getCell('A'.$row_no)->getValue());
            if ($n) {
                if ($n != '序号') {
                    foreach (range('A', 'H') as $v) {
                        $item[$v] = trim($sheet->getCell($v.$row_no)->getValue());
                    }

                    $data[] = $item;
                }
            }
            $row_no++;
        }
        /*2020年*/
        if ($year == 2020) {
            if ($type == 1) {
                foreach ($data as $k => $v) {
                    $toTimestamp = Date::excelToTimestamp($v['D']);
                    $date = date("Y-m-d", $toTimestamp );

                    $model = ChenHonesty2020A1::firstOrNew(['c1'=>$v['B']]);
                    $model->c1 = $v['B'];
                    $model->c2 = $v['C'];
                    $model->c3 = $date;
                    $model->c4 = $v['E'];
                    $model->c5 = $v['F'];
                    $model->c6 = $v['G'];
                    $model->c7 = $v['H'];
                    $model->save();
                }
            } elseif ($type == 2) {
                foreach ($data as $k => $v) {
                    $toTimestamp = Date::excelToTimestamp($v['F']);
                    $date = date("Y-m-d", $toTimestamp );

                    $model = ChenHonesty2020A2::firstOrNew(['c1'=>$v['B']]);
                    $model->c1 = $v['B'];
                    $model->c2 = $v['C'];
                    $model->c3 = $v['D'];
                    $model->c4 = $v['E'];
                    $model->c5 = $date;
                    $model->c6 = $v['G'];
                    $model->c7 = $v['H'];
                    $model->save();
                }
            }
        }
        return redirect('/account/honesty?year='.$year)->with('trash', '同步成功！本次共同步 '.count($data).' 条数据');
    }

    public function chart()
    {
        $page = [
            'page_name' => $this->page_name,
            'title' => '报表' . $this->page_title
        ];
        return view('account.honesty.chart', [
            'page' => $page,
        ]);
    }

}

