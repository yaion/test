<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TaskController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function list(Request $request)
    {
        $request->validate([
            'token' => 'required',
        ]);
        // 获取今天的日期
        $today = now()->toDateString();
        $data = Redis::get($request->token);
        $userData = json_decode($data,true);
        // 检查任务是否已完成
        $task = Task::where('user_id', $userData["id"])
            ->whereDate('created_at', $today)
            ->get();
        return response()->json(['code' => 0, 'message' => '成功', 'data' => $task]);
    }

    public function complete(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'token' => 'required',
        ]);
        $data = Redis::get($request->token);
        $userData = json_decode($data,true);

        // 获取今天的日期
        $today = now()->toDateString();

        // 检查任务是否已完成
        $task = Task::where('user_id', $userData["id"])
            ->where('id', $request->id)
            ->where("status",0)
            ->whereDate('created_at', $today)
            ->first();
        if ($task) {
            return response()->json(['code' => -1, 'message' => '系统错误请重试']);
        }
        // 领取奖励
        $task->status = 1;
        $task->save();
        //添加元气值
        $result = DB::table("user")->where("id", $userData["id"])->update(['energy' => DB::raw("energy + {$task->energy_value}")]);
        if($result == 0){
            return response()->json(['code' => -1, 'message' => '系统错误请重试']);
        }

        return response()->json(['code' => 0, 'message' => '任务完成', 'data' => ['energy_value' => $task->energy_value]]);
    }

    public function addTask(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'task_type' => 'required|in:1,2,3,4,5',
        ]);
        // 获取今天的日期
        $today = now()->toDateString();
        $data = Redis::get($request->token);
        $userData = json_decode($data,true);
        // 检查任务是否已完成

        $taskData = Task::where('user_id', $userData["id"])
            ->where("task_type",$request->task_type)
            ->whereDate('created_at', $today)
            ->get();
        if(!$taskData){
            return response()->json(['code' => -1, 'message' => '当前类型今日任务已完成！']);
        }
        $task = new Task();
        $task -> user_id = $userData['id'];
        $task -> task_type = $request->task_type;
        $task -> energy_value= 10;
        $task->save();
        return response()->json(['code' => 0, 'message' => '添加任务成功', 'data' => []]);
    }

}
