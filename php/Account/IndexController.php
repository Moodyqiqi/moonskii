<?php
namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IndexController extends Controller
{
    protected $UserTable = 'abel_chen_user';
    protected $ReportTable = 'abel_chen_report';
    protected $QueryTable = 'abel_chen_query';
    protected $HonestyTable = 'abel_chen_honesty';
    protected $TZTable = 'abel_chen_statistic';

    // 页面显示
    public function index(Request $request)
    {
        $page_name = 'index';
        $page['page_name'] = $page_name;
        $row = [];

        $total1 = DB::table($this->UserTable)->count('id'); // 用户
        $total2 = DB::table($this->ReportTable)->count('id'); // 信访
        $total3 = DB::table($this->QueryTable)->count('id'); // 咨询
        $total4 = DB::table($this->HonestyTable)->count('id'); // 廉洁从业鉴定
        $total5 = DB::table($this->TZTable)->count('id'); // 信访台账统计

        $year = (int) $request->input('year');
        if (empty($year)) {
            $year = date('Y');
        }
        $btime = mktime(0,0,0,1,1, $year);
        $etime = mktime(23,59,59,12,31, $year);
        $report_chart_year = $this->getLabel($btime, $etime, 'month');
        $c1 = $this->getChartData1($this->ReportTable, $year);
        $c2 = $this->getChartData1($this->QueryTable, $year);
        $c3 = $this->getChartData2($this->HonestyTable, $year, 'riqi');


        return view('account.index', [
            'page' => $page,
            'panel' => [
                [
                    'icon' => 'fa fa-user',
                    'text' => '用户总数',
                    'num' => $total1,
                ],
                [
                    'icon' => 'fa fa-commenting',
                    'text' => '信访举报总数',
                    'num' => $total2,
                ],
                /*[
                    'icon' => 'fa fa-question-circle',
                    'text' => '咨询总数',
                    'num' => $total3,
                ],*/
                [
                    'icon' => 'fa fa-question-circle',
                    'text' => '廉洁从业鉴定',
                    'num' => $total4,
                ],
                [
                    'icon' => 'fa fa-question-circle',
                    'text' => '信访台账统计',
                    'num' => $total5,
                ],
            ],
            'chart' => [
                'report_table_year' => [
                    'title' => '每月网上信访举报数（'.$year.'）',
                    'data' => $c1
                ],
                'query_table_year' => [
                    'title' => '每月网上咨询数（'.$year.'）',
                    'data' => $c2
                ],
                'honesty_table_year' => [
                    'title' => '每月廉洁从业鉴定数（'.$year.'）',
                    'data' => $c3
                ],
            ]

        ]);
    }

    public function getChartData2($table, $year, $column='created_at')
    {
        $btime = mktime(0,0,0,1,1, $year);
        $etime = mktime(23,59,59,12,31, $year);
        $label = $this->getLabel($btime, $etime, 'month');
        $data = DB::select("SELECT FROM_UNIXTIME($column,'%Y-%m') AS oaWonVEI,COUNT(id) AS tX1xYm5Q FROM $table WHERE $column >= $btime AND $column<=$etime GROUP BY oaWonVEI");
        $res = [];
        $r1 = [];
        foreach ($data as $k => $v) {
            $r1[$v->oaWonVEI] = $v->tX1xYm5Q;
        }
        foreach ($label as $k => $v) {
            $res[] = [
                'label' => $v,
                'value' =>  @$r1[$v] ?: 0,
            ];
        }
        return $res;
    }

    public function getChartData1($table, $year)
    {
        $btime = mktime(0,0,0,1,1, $year);
        $etime = mktime(23,59,59,12,31, $year);
        $label = $this->getLabel($btime, $etime, 'month');
        $data = DB::select("SELECT FROM_UNIXTIME(created_at,'%Y-%m') AS oaWonVEI,COUNT(id) AS tX1xYm5Q FROM $table WHERE created_at >= $btime AND created_at<=$etime GROUP BY oaWonVEI");
        $res = [];
        $r1 = [];
        foreach ($data as $k => $v) {
            $r1[$v->oaWonVEI] = $v->tX1xYm5Q;
        }
        foreach ($label as $k => $v) {
            $res[] = [
              'label' => $v,
              'value' =>  @$r1[$v] ?: 0,
            ];
        }
        return $res;
    }

    public function getChartData()
    {
        $total1 = DB::table($this->UserTable)->count('id'); // 用户
        $total2 = DB::table($this->ReportTable)->count('id'); // 信访
        $total3 = DB::table($this->QueryTable)->count('id'); // 咨询

        $btime = strtotime(date('Y-m-d')) - 86400*30;
        $etime = strtotime(date('Y-m-d'));
        $user = $this->query1($this->UserTable, [$etime-86400*7, $etime]);
        $report = $this->query1($this->ReportTable, [$etime-86400*7, $etime]);
        $zixun = $this->query1($this->QueryTable, [$etime-86400*7, $etime]);
        $m = date('m');
        $y = date('Y');
        $ybtime = mktime(0,0,0,$m-11,1, $y);
        // $ybtime =

        return [
            'msg'=>'SUCCESS', 'status'=>'SUCCESS',
            'totalData' => [
                'user' => $total1,
                'report' => $total2,
                'zixun' => $total3,
            ],
            'chartLabel' => $this->getLabel($etime-86400*7, $etime),
            'chartData' => [
                'user' => $user,
                'report' => $report,
                'zixun' => $zixun,
            ],
            // 按月
            'chartLabelMonth' => $this->getLabel($etime-86400*30, $etime),
            'chartDataMonth' => [
                'user' => $this->query1($this->UserTable, [$etime-86400*30, $etime]),
                'report' => $this->query1($this->ReportTable, [$etime-86400*30, $etime]),
                'zixun' => $this->query1($this->QueryTable, [$etime-86400*30, $etime]),
            ],
            // 按年
            'chartLabelYear' => $this->getLabel($ybtime, $etime, 'month'),
            'chartDataYear' => [
                'user' => $this->query1($this->UserTable, [$ybtime, $etime], 'month'),
                'report' => $this->query1($this->ReportTable, [$ybtime, $etime], 'month'),
                'zixun' => $this->query1($this->QueryTable, [$ybtime, $etime], 'month'),
            ],
        ];
    }
    public function getLabel($btime, $etime, $type=null)
    {
        $day = (int)($etime-$btime)/86400;
        $label = [];
        if ($type == 'month') {
            for ($i=0;$i<$day;$i++) {
                $d = date('Y-m', $btime+86400*$i);
                $label[] = $d;
            }
        } else {
            for ($i=0;$i<$day;$i++) {
                $d = date('Y-m-d', $btime+86400*$i);
                $label[] = $d;
            }
        }
        return array_values(array_unique($label));
    }
    public function query1($table, $time, $type=null)
    {
        $btime = $time[0];
        $etime = $time[1];
        if ($type=='month') {
            return DB::select("SELECT FROM_UNIXTIME(created_at,'%Y-%m') AS riqi,COUNT(id) AS count FROM $table WHERE created_at >= $btime AND created_at<=$etime GROUP BY riqi");
        }
        return DB::select("SELECT FROM_UNIXTIME(created_at,'%Y-%m-%d') AS riqi,COUNT(id) AS count FROM $table WHERE created_at >= $btime AND created_at<=$etime GROUP BY riqi");
    }

    public function query2()
    {
        $time = time() - 86400*30;
        return DB::select("SELECT COUNT(id) AS count FROM $table WHERE created_at >= $btime AND created_at<=$etime GROUP BY riqi");
    }

}

