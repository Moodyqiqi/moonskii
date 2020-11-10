<?php
namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Model\Account;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    // 页面显示
    public function index(Request $request)
    {
        return view('account.login');
    }
    // get登陆页
    public function login()
    {
        if (session('account.id')){
            return redirect('/account/');
        }
        return view('account.login');
    }
    public function ajaxLogin(Request $request)
    {
        $txtCode =session('login_code');
        session()->forget('login_code');
        if (empty($txtCode)) {
            return fs400('尚未通过登陆验证，如果是多次出现该问题请联系管理员！');
        }
        $username = $request->input('username');
        $username = trim($username);
        $password = $request->input('password');
        $password = trim($password);
        $token = $request->header('x-xsrf-token');
        $username = base64_decode(base64_decode($username));
        $password = base64_decode(base64_decode($password));
        $txt = substr($username, -8);
        $username = substr($username, 0, -8);
        $password = substr($password, 0, -8);
        if (empty($username) || empty($password)) {
            return fs400('请输入用户名或密码');
        }
        if ($txt !== $txtCode) {
            return fs400('尚未通过登陆验证，如果是多次出现该问题请联系管理员！');
        }
        $account = Account::where(["username"=>$username])->first();
        if ( $account && $account->password ) {
            if ( !$account->checked ) {
                return fs400('账户尚未审核通过，请联系管理员！');
            }
            $bool = password_verify($password, $account->password);
            if ( $bool ) {
                $account->password = bcrypt($password);
                $account->access_token = Str::random(32);
                $account->save();
                session(['account'=>$account->for_session]);
                return fs200('登陆成功！');
            }
        }
        return fs400('用户名或密码错误！');
    }

    protected function initLogin()
    {
        $txtCode = Str::random(8);
        session(['login_code'=>$txtCode]);
        return ['status'=>'SUCCESS', 'data'=>$txtCode];
    }

    // post登陆
    public function dologin(Request $request)
    {
        $check = session()->get('clicaptcha_check');
        $username = $request->input('username');
        $username = trim($username);
        $password = $request->input('password');
        $password = trim($password);
        $ajax = $request->header('x-requested-with');
        $t = $request->input('t');
        if ($t == 'ajax') {
            return $this->ajaxLogin($request);
        }
        if (!$check) {
            if (!empty($ajax)) {
                return fs400('尚未通过登陆验证');
            }
            return back()->withInput()->with('trash', '错少参数');
        }

        if ($t == 'init') {
            return $this->initLogin();
        }

        if ( !empty($username) && !empty($password) ) {
            $account = Account::where(["username"=>$username])->first();
            if ( $account && $account->password ) {
                if ( !$account->checked ) {
                    return back()->withInput()->with('trash', '账户尚未审核通过');
                }
                $bool = password_verify($password, $account->password);
                if ( $bool ) {
                    $account->password = bcrypt($password);
                    $account->access_token = Str::random(32);
                    $account->save();
                    session(['account'=>$account->for_session]);
                    return redirect('/account/index');
                }
            }
            return back()->withInput()->with('trash', '用户名或密码错误');
        }
        return back()->withInput()->with('trash', '请输入用户名及密码');
    }
    // 退出
    public function loginout()
    {
        $bool = session()->pull('account');
        return redirect('/account/login');
    }
}

