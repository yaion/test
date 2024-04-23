<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;
use Carbon\Carbon as Carbons;

class UserController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function index(Request $request)
    {
        // 验证请求参数
        $request->validate([
            'token' => 'required',
        ]);
        // 获取 Redis 键值对
        $data = Redis::get($request->token);
        $userData = json_decode($data, true);

        $result = User::select("id", "nick_name", "energy")->where("id", $userData['id'])->first();


        return response()->json(['code' => 0, 'message' => '获取数据成功', 'data' => $result]);
    }

    public function register(Request $request)
    {
        // 验证请求参数
        $request->validate([
            'nick_name' => 'required',
            'password' => 'required',
            'register_code' => 'nullable'
        ]);

        // 检查是否存在相同的 nick_name
        $existingUser = User::where('nick_name', $request->nick_name)->exists();
        if ($existingUser) {
            return response()->json(['code' => -1, 'message' => '该昵称已被注册，请使用其他昵称']);
        }

        // 创建用户
        $user = new User();
        $user->nick_name = $request->nick_name;
        $user->password = bcrypt($request->password);
        $user->save();

        $registerCode = $request->register_code;

        // 根据注册码给邀请人添加元气任务
        if ($registerCode) {
            $code = Redis::get($registerCode);
            $codeData = json_decode($code, true); // 修改此行
            if (!$code || $codeData["tnx"] <= 0) {
                return response()->json(['code' => -1, 'message' => '分享码有问题，请重试！']);
            }
            if (isset($codeData["tnx"])) { // 修改此行
                $codeData["tnx"] = $codeData["tnx"] -= 1;
                $current = Carbons::now(); // 修改此行
                // 获取第二天的 00:00:00
                $nextDay = $current->copy()->addDay()->startOfDay();
                // 计算两个时间的差值（秒数）
                $seconds = $nextDay->diffInSeconds($current);
                $task = new Task();
                $task->user_id = $codeData['user_id'];
                $task->task_type = 4;
                $task->energy_value = $task->EnergyValue["4"];
                $task->save();
                Redis::setex($registerCode, $seconds, json_encode($codeData));
            }
        }

        return response()->json(['code' => 0, 'message' => '注册成功']);
    }



    public function login(Request $request)
    {
        // 验证请求参数
        $request->validate([
            'nick_name' => 'required',
            'password' => 'required',
        ]);

        // 开启事务
        DB::beginTransaction();

        try {
            // 验证用户
            $user = User::select("id", "nick_name", "password", "energy")->where('nick_name', $request->nick_name)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json(['code' => -1, 'message' => '用户名或密码错误']);
            }

            // 生成 token
            $token = md5($user->nick_name . time());
            $data = json_encode($user);
            Redis::setex($token, env("REDIS_EXP", 3600), $data);

            // 每日登录任务
            $task = Task::where('user_id', $user->id)
                ->where('task_type', 1)
                ->whereDate('created_at', Carbon::today())
                ->first();

            if (!$task) {
                $task = new Task();
                $task->user_id = $user->id;
                $task->task_type = 1;
                $task->energy_value = $task->EnergyValue["1"];
                $task->save();
            }

            // 提交事务
            DB::commit();

            return response()->json(['code' => 0, 'message' => '登录成功', 'data' => ["token" => $token]]);
        } catch (\Exception $e) {
            // 发生异常时回滚事务
            DB::rollBack();
            return response()->json(['code' => -1, 'message' => '登录失败，请重试']);
        }
    }

    public function complete(Request $request)
    {
        // 验证请求参数
        $request->validate([
            'nick_name' => 'required',
            'gender' => 'required',
            'language' => 'required',
            'city' => 'required',
            'province' => 'required',
            'country' => 'required',
            'avatarUrl' => 'required',
            'unionId' => 'required',
            'phone' => 'required',
            'token' => 'required',
        ]);

        // 开启事务
        DB::beginTransaction();

        try {
            // 获取 Redis 键值对
            $data = Redis::get($request->token);
            $userData = json_decode($data,true);

            // 查询用户
            $user = User::find($userData["id"]);
            // 如果用户状态为 1，则添加任务信息
            if($user->status != 1){
                $user->energy += 10;

                // 添加任务信息
                $task = new Task();
                $task->user_id = $userData["id"];
                $task->task_type = 3;
                $task->energy_value = $task->EnergyValue["3"];
                $task->save();
            }

            // 设置用户信息
            $user->nick_name = $request->nick_name;
            $user->gender = $request->gender;
            $user->language = $request->language;
            $user->phone = $request->phone;
            $user->city = $request->city;
            $user->country = $request->country;
            $user->avatarUrl = $request->avatarUrl;
            $user->status = 1;
            // 保存用户信息
            $user->save();
            // 提交事务
            DB::commit();

            return response()->json(['code' => 0, 'message' => '完善信息成功']);
        } catch (\Exception $e) {
            // 发生异常时回滚事务
            DB::rollBack();
            return response()->json(['code' => -1, 'message' => '完善信息失败，请重试']);
        }
    }

    public function inviteUsers(Request $request)
    {
        // 验证请求参数
        $request->validate([
            'token' => 'required',
        ]);

        // 获取 Redis 键值对
        $data = Redis::get($request->token);
        $userData = json_decode($data,true);

        // 查询用户
        $user = User::find($userData["id"]);

        $registerCode = md5($userData["nick_name"]."register_code");
        // 获取 Redis 键值对
        $code = Redis::get($registerCode);
        $codeData = json_decode($code, true); // 修改此行
        if(!$code){
            $codeData=[
                "user_id"=>  $userData["id"],
                "tnx" => 3,
            ];
        }
        if(isset($codeData["tnx"]) && $codeData["tnx"] > 0){ // 修改此行
            $current = Carbons::now(); // 修改此行
            // 获取第二天的 00:00:00
            $nextDay = $current->copy()->addDay()->startOfDay();
            // 计算两个时间的差值（秒数）
            $seconds = $nextDay->diffInSeconds($current);

            Redis::setex($registerCode, $seconds, json_encode($codeData)); // 修改此行
        }else{
            return response()->json(['code' => -1, 'message' => '生成分享链接失败，次数超过3次', 'data' =>[]]);
        }

        return response()->json(['code' => 0, 'message' => '生成分享链接成功', 'data' => ["InviteLink" => "http://127.0.0.1:8000/user/index?register_code={$registerCode}"]]);
    }

}
