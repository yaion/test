<?php

namespace App\Http\Controllers;

use App\Models\Orange;
use App\Models\User;
use Carbon\Carbon as Carbons;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;

class OrangeController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function receive(Request $request)
    {
        $request->validate([
            'token' => 'required',
        ]);
        $data = Redis::get($request->token);
        $userData = json_decode($data,true);
        $orange = new Orange();
        // 检查用户是否已领取元气橙苗
        $orangeData = $orange->where([
            ['user_id', '=', $userData['id']],
            ['growth_stage', '<=', $orange->orangesType[3]],
            ['growth_days','<',21]
        ])->first();
        if ($orangeData) {
            return response()->json(['code' => -1, 'message' => '领取失败，已领取过元气橙苗']);
        }

        // 领取元气橙苗
        $orange->user_id = $userData['id'];
        $orange->growth_stage = 0;
        $orange->growth_days = 0;
        $orange->progress = 0;
        $orange->save();

        return response()->json(['code' => 0, 'message' => '领取成功']);
    }

    public function list(Request $request)
    {
        $request->validate([
            'token' => 'required',
            "type" => 'required|in:1,2',
        ]);
        $data = Redis::get($request->token);
        $userData = json_decode($data,true);
        $orange = new Orange();
        // 检查用户是否已领取元气橙苗
        $orangeData = [];
        //未完成的
        if($request->type == 1){
            $orangeData = $orange->where([
                ['user_id', '=', $userData['id']],
                ['growth_stage', '<=', 3],
                ['growth_days','<',21]
            ])->get();
        }
        //已完成的
        if($request->type == 2){
            $orangeData = $orange->where([
                ['user_id', '=', $userData['id']],
                ['growth_stage', '=', 3],
                ['growth_days','=',21]
            ])->get();
        }

        return response()->json(['code' => 0, 'message' => '成功',"data"=>$orangeData]);
    }

    public function water(Request $request)
    {
        $request->validate([
            'token' => 'required',
            "id"=>'required',
        ]);
        $data = Redis::get($request->token);
        $userData = json_decode($data,true);
        $orange = new Orange();

        //判断是否已浇水
        $key = $userData['id'];
        $value = Redis::get($key);
        if($value){
            return response()->json(['code' => -1, 'message' => '今日橙长获取已完成！']);
        }
        // 检查用户是否已领取元气橙苗
        $orangeData = $orange->where([
            ['user_id', '=', $userData['id']],
            ['id', '=', $request->id],
            ['growth_stage','<=',$orange->orangesType[3]],
            ['growth_days','<',21]
        ])->first();
        $userData = User::find($userData['id']); // 获取用户信息
        if(!$orangeData){
            return response()->json(['code' => -1, 'message' => '当前树已长成,或不存在！']);
        }
        //根据不同阶段，更新数据
        switch ($orangeData->growth_stage) {
            case 0:
                //橙苗阶段需灌溉3天，每日消耗40元气值
                $result = DB::table("user")->where([
                    ['id', '=', $userData['id']],
                    ['energy', '>=', 40]
                ])->update(['energy' => DB::raw("energy - 40")]);
                if($result == 0){
                    return response()->json(['code' => -1, 'message' => '你的元气值不够，请去做任务！']);
                }
                $orangeData->growth_days+=1;
                $day = $orangeData->growth_days;
                $orangeData->progress="{$day}/3";
                if($orangeData->growth_days=3){
                    $orangeData->growth_stage=1;
                    $orangeData->progress="0/5";
                    $addEnergy = 50;
                }
                break ;
            case 1:
                //橙花阶段需灌溉5天，每日消耗60元气值
                $result = DB::table("user")->where([
                    ['id', '=', $userData['id']],
                    ['energy', '>=', 60]
                ])->update(['energy' => DB::raw("energy - 60")]);
                if($result == 0){
                    return response()->json(['code' => -1, 'message' => '你的元气值不够，请去做任务！']);
                }
                $orangeData->growth_days+=1;
                $day = $orangeData->growth_days-3;
                $orangeData->progress="{$day}/5";
                if($orangeData->growth_days=8){
                    $orangeData->growth_stage=2;
                    $orangeData->progress="0/6";
                    $addEnergy = 80;
                }
                break ;
            case 2:
                //半熟阶段需灌溉6天，每日消耗70元气值
                $result = DB::table("user")->where([
                    ['id', '=', $userData['id']],
                    ['remain_count', '>=', 70]
                ])->update(['energy' => DB::raw("energy - 60")]);
                if($result == 0){
                    return response()->json(['code' => -1, 'message' => '你的元气值不够，请去做任务！']);
                }
                $orangeData->growth_days+=1;
                $day = $orangeData->growth_days-8;
                $orangeData->progress="{$day}/6";
                if($orangeData->growth_days=14){
                    $orangeData->growth_stage=3;
                    $orangeData->progress="0/7";
                    $addEnergy = 100;
                }
                break ;
            case 3:
                //完熟阶段需灌溉7天，每日消耗80元气值
                $result = DB::table("user")->where([
                    ['id', '=', $userData['id']],
                    ['energy', '>=', 80]
                ])->update(['energy' => DB::raw("energy - 80")]);
                if($result == 0){
                    return response()->json(['code' => -1, 'message' => '你的元气值不够，请去做任务！']);
                }
                $orangeData->growth_days+=1;
                $day = $orangeData->growth_days-14;
                $orangeData->progress="{$day}/7";
                break ;
            default:
                return response()->json(['code' => -1, 'message' => '系统错误，请重试！']);
        }
        $result = DB::table("user")->where([
            ['id', '=', $userData['id']]
        ])->update(['energy' => DB::raw("energy + {$addEnergy}")]);
        if($result == 0){
            return response()->json(['code' => -1, 'message' => '你的元气值不够，请去做任务！']);
        }
        $orangeData->save();
        $current = Carbons::now(); // 修改此行
        // 获取第二天的 00:00:00
        $nextDay = $current->copy()->addDay()->startOfDay();
        // 计算两个时间的差值（秒数）
        $seconds = $nextDay->diffInSeconds($current);
        Redis::setex($key,$seconds , 1);
        return response()->json(['code' => 0, 'message' => '成功',"data"=>[]]);
    }

}
