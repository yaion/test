<?php

namespace App\Http\Service;

use App\Models\User as UserModel;
use Illuminate\Support\Facades\Redis;
use function Laravel\Prompts\select;

class UserService
{

    public function index(){

        //Redis::setex('key', 1, 'value');
        //sleep(2);
        // 获取 Redis 键值对
        $value = Redis::get('key');
        var_dump(env("REDIS_EXP",1600));

        $data = UserModel::where("id",1)->take(10)->get();

        return $data;
    }

}
