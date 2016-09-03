<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WeChat extends Model
{
    //
    protected $table= 'wx_article';
    public function scopePublish($query)
    {
        return $query->where('audit', '1')
            ->where('del', '0')
            ->where('online', '1');
    }
}
