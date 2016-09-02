<?php

namespace App\Http\Controllers;

use App\WeChat\Response;
use Illuminate\Http\Request;

//use App\Http\Requests;
use DB;
use App\WeChat\Tour;
//use App\Http\Requests;

class ArticlesController extends Controller
{
    public function index()
    {
//        $articles = Article::all();
        $articles = DB::table('wx_article')
            ->where('title', 'like', '门票%')
            ->orderBy('id', 'desc')
            ->skip(0)->take(2)->get();
//        return $articles;
        return $articles;
    }

    public function  show($id)
    {
        $article = WechatArticle::find($id);
//        $aaa=WechatArticle::
//        return $article;
        return view('articles.show', compact('article'));
    }

    public function detail($id)
    {
        $article = WechatArticle::find($id);
        return view('articles.detail', compact('article'));
    }

    public function info()
    {
        $flag = "不包含";
        $row = DB::table('wx_request_keyword')
            ->orderBy('id', 'asc')->get();

        foreach ($row as $result) {
            if (@strstr('sda', $result->keyword) != '') {
                $flag = $result->keyword;
                break;
            }
        }
        return $flag;
    }

}
