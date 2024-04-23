<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'order';
}
