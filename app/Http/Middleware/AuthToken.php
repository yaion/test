<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;


class AuthToken
{
    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function handle(Request $request, Closure $next)
    {
            $data = Redis::get($request->input("token"));
            if (!$data) {
                return response()->json(['code' => -1, 'message' => '未登录']);
            }
            // 将用户信息存入 Redis，并设置过期时间
            Redis::setex($request->input("token"), env("REDIS_EXP",3600), $data);
            return $next($request);

    }
}
