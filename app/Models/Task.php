<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'tasks';

    public array $EnergyValue=[
        "1" => 5,//登录小程序 5
        "2" => 10,//查看微信公众号 10
        "3" => 10,//完善个人资料，10元气值
        "4" => 5,//邀请好友（每日3次机会），每次5元气值
        "5" => 5,//观看视频（每日1次机会），10元气值
    ];
}
