<?php

namespace App\Http\Controllers;

use App\Models\Orange;
use App\Models\Order;
use App\Models\Prize;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;

class PrizeController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;
    public function list(Request $request)
    {
        $request->validate([
            'token' => 'required',
        ]);
        $prizes = Prize::all();
        return response()->json(['code' => 0, 'message' => '查询成功', 'data' => $prizes]);
    }

    public function exchange(Request $request)
    {
        $request->validate([
            'token' => 'required',
            "prize_id" => 'required',
        ]);
        $data = Redis::get($request->token);
        $userData = json_decode($data,true);
        // 检查奖品是否已领取
        $order = new Order();
        $orderData = $order->where([
            ['user_id', '=', $userData['id']],
            ['prizes_id', '=', $request->prize_id]
        ])->count();
        if($orderData){
            return response()->json(['code' => -1, 'message' => '兑换失败，请查看是否符合要求！']);
        }

        // 检查用户树是否足够，奖品是否足够
        $orange = new Orange();
        $treeNum =  $orange->where([
            ['user_id', '=', $userData['id']],
            ['growth_stage', '=', $orange->orangesType[3]],
            ['growth_days', '=', 21]
        ])->count();

        $prizeData = DB::table("prizes")->where([
            ['id', '=', $request->prize_id],
            ['tree_count', '=', $treeNum],
        ])->first();
        if(!$prizeData){
            return response()->json(['code' => -1, 'message' => '兑换失败，请查看是否符合要求！']);
        }
        // 开启事务
        DB::beginTransaction();

        try {
            // 减少奖品剩余数量
            $result = DB::table("prizes")
                ->where('id', $request->prize_id)
                ->where('remain_count', '>', 0)
                ->update(['remain_count' => DB::raw('remain_count - 1')]);

            if ($result > 0) {
                // 更新成功
                // 添加兑换记录
                $order->user_id = $userData['id'];
                $order->tree_count = $treeNum;
                $order->prize_id = $request->prize_id;
                $order->description = $prizeData->description;
                $order->save();
            } else {
                // 更新失败
                // 还原橙树数量
                $orange->where([
                    ['user_id', '=', $userData['id']],
                    ['growth_stage', '=', $prizeData->tree_stage]
                ])->take($prizeData->tree_count)->delete();

                // 给用户赠送元气值
                DB::table("users")->where("id", $userData['id'])->increment('energy', $prizeData->energy_value);
            }

            // 提交事务
            DB::commit();

            return response()->json(['code' => 0, 'message' => '兑换成功']);
        } catch (\Exception $e) {
            // 发生异常时回滚事务
            DB::rollBack();
            return response()->json(['code' => -1, 'message' => '兑换失败，请重试！']);
        }
    }
}
