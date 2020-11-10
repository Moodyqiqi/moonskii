<?php
namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Model\XunchaJubao;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class XunchaController extends Controller
{
    protected $UserTable = 'abel_chen_user';
    protected $ReportTable = 'abel_chen_report';
    protected $QueryTable = 'abel_chen_query';
    protected $HonestyTable = 'abel_chen_honesty';
    protected $TZTable = 'abel_chen_statistic';
    protected $suffix = '_巡查专项举报';

    public function jubao(Request $request)
    {
        $page = [
            'page_name' => 'xuncha/jubao',
            'title' => '监督举报' . $this->suffix
        ];

        $mod = new XunchaJubao();

        if ($request->input('trashed') == 1) {
            $mod = $mod->onlyTrashed();
        }
        $name = trim($request->input('name'));
        if (!empty($name)) {
            $mod = $mod->where('c1', 'like', '%'.$name.'%');
        }

        $data = $mod->paginate(25);

        return view('account.xuncha.jubao', [
            'page' => $page,
            'data' => $data,
        ]);
    }

    public function jubao_detail(Request $request, $id)
    {
        $model = XunchaJubao::findOrFail($id);
        return view('account.xuncha.jubao_detail', ['data'=>$model]);
    }

}

