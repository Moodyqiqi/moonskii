<?php
namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Model\ChenHonesty2020A1;
use App\Model\ChenHonesty2020A2;
use App\Model\ChenHonesty;
use App\Model\Ssjt;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AjaxController extends Controller
{
    protected $get = [
    ];
    protected $delete = [
    ];
    protected $put = [
    ];
    protected $post = [
        'honesty'
    ];

    public function index(Request $request, $action)
    {
        $do = $action;
        $method = $request->method();
        $method = strtolower($method);
        if ($method=='get') {
            if (in_array($do, $this->get)) {
                return $this->$do($request);
            }
        }
        if ($method=='delete') {
            if (in_array($do, $this->delete)) {
                return $this->$do($request);
            }
        }
        if ($method=='put') {
            if (in_array($do, $this->put)) {
                return $this->$do($request);
            }
        }
        if ($method=='post') {
            if (in_array($do, $this->post)) {
                return $this->$do($request);
            }
            if (in_array($do, $this->delete)) {
                return $this->$do($request);
            }
            if (in_array($do, $this->put)) {
                return $this->$do($request);
            }
        }
        return [$do];
    }

    public function honesty(Request $request)
    {
        $t = $request->input('t');
        $year = (int) $request->input('year');
        $id = (int) $request->input('id');
        $type = $request->input('type');
        if ($t == 'getInfo') {
            if ($year == 2020) {
                if ($type == 2) {
                    $model = ChenHonesty2020A2::find($id);
                    $l = [
                        'c1' => '监督编码',
                        'c2' => '被征求意见人姓名',
                        'c3' => '工作单位及职务',
                        'c4' => '征求意见事由',
                        'c5' => '意见回复时间',
                        'c6' => '回复意见',
                        'c7' => '备注',
                    ];
                } else {
                    $model = ChenHonesty2020A1::find($id);
                    $l = [
                        'c1' => '监督编码',
                        'c2' => '函号',
                        'c3' => '回复时间',
                        'c4' => '回复党风廉政意见人数',
                        'c5' => '征求意见事由',
                        'c6' => '征求意见的来函单位名称',
                        'c7' => '备注',
                    ];
                }
                if (empty($model->id)) {
                    return fs404('没有找到');
                }
                $data = $this->makeLV([$model], $l);
                return fs200d('成功', $data[0]);
            }
        } elseif ($t == 'showChart') {
            if ($year == 2020) {
                $pt1 = ChenHonesty2020A1::get();
                $pt2 = ChenHonesty2020A2::get();
                $mon = range(1,12);
                $rt1 = ma1(); // 根据月份合并回复数量
                $rt2 = ma1(); // 根据月份合并人数
                $rt3 = []; // 征求事由数量
                $rt4 = []; // 征求事由合并人数
                $rt5 = []; // 征求事由合并人数
                $rt6 = []; // 征求事由合并人数
                foreach ($pt1 as $k => $v) {
                    $m1 = date('m', strtotime($v->c3));
                    $m1 = (int) $m1;
                    $rt1[$m1] += 1;
                    $rt2[$m1] += $v->c4;
                    if (empty($rt3[$v->c5])) {
                        $rt3[$v->c5] = 1;
                    } else {
                        $rt3[$v->c5] += 1;
                    }
                    if (empty($rt4[$v->c5])) {
                        $rt4[$v->c5] = $v->c4;
                    } else {
                        $rt4[$v->c5] += $v->c4;
                    }
                    if (empty($rt5[$v->c6])) {
                        $rt5[$v->c6] = 1;
                    } else {
                        $rt5[$v->c6] += 1;
                    }
                    if (empty($rt6[$v->c6])) {
                        $rt6[$v->c6] = $v->c4;
                    } else {
                        $rt6[$v->c6] += $v->c4;
                    }
                }


                return fs200d('成功', [
                    'total' => [
                        [
                            'name' => '党风廉政意见回复',
                            'value' => count($pt1)
                        ],
                        [
                            'name' => '党风廉政意见回复提出暂缓或否定性意见',
                            'value' => count($pt2)
                        ],
                    ],

                    'chart' => [
                        'c1' => fk2($rt1),
                        'c2' => fk2($rt2),
                        'c3' => fk1($rt3),
                        'c4' => fk1($rt4),
                        'c5' => fk1($rt5),
                        'c6' => fk1($rt6),
                    ],
                ]);
            }

        }

        return fs200('成功');
    }

    protected function makeLV($data, $label)
    {
        $res = [];
        foreach ($data as $k => $v) {
            $item = [];
            foreach ($label as $j => $m) {
                $item[] = [
                    'label' => $m,
                    'value' => $v->$j
                ];
            }
            $res[] = $item;
        }
        return $res;
    }


}

