<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Orange extends Model
{
    use HasFactory;

    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'oranges';

    //0-橙苗，1-橙花，2-半熟，3-完熟
    public  array $orangesType=[0,1,2,3];
}
