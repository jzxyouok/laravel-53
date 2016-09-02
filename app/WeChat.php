<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WeChat extends Model
{
    //
    public function scopePublish($query)
    {
        return $query->where('online', '1');
    }
}
