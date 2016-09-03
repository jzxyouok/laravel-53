<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WeChat extends Model
{
    //
    protected $table= 'wx_articel';
    public function scopePublish($query)
    {
        return $query->where('online', '1');
    }
}
