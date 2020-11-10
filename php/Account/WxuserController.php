<?php
namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Model\Account;
use App\Model\ChenUser as Model;
use App\Model\Ssjt;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WxuserController extends Controller
{
    protected $suffix = '';
    protected $page_title = '微信用户管理';
    protected $page_name = 'wxuser';
    protected $view_index = 'account.wxuser.index';
    protected $view_show = 'account.wxuser.show';
    protected $view_edit = 'account.wxuser.edit';
    protected $view_create = 'account.wxuser.create';

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

}

