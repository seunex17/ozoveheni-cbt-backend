<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $fillable = [
        'exam_id',
        'serial',
        'pin',
        'reference',
    ];
}
