<?php
namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Model\XunchaJubao as Model;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class XunchaJubaoController extends Controller
{
    protected $suffix = '_巡查专项举报';
    protected $page_title = '监督举报';
    protected $page_name = 'xuncha/jubao';
    protected $view_index = 'account.xuncha.jubao.index';
    protected $view_show = 'account.xuncha.jubao.show';
    protected $view_edit = 'account.xuncha.jubao.edit';

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
        $page = [
            'page_name' => $this->page_name,
            'title' => '查看' . $this->page_title
        ];
        return view($this->view_edit, [
            'page' => $page,
            'data'=>$model,
        ]);
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

}

