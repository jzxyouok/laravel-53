<?php

namespace App\Http\Controllers;

use App\WeChat\Response;
use Illuminate\Http\Request;

//use App\Http\Requests;
use DB;
use App\WeChat;
use App\User;
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
      /* $row_news = WeChat::where('msgtype', 'news')
            ->popular()
            ->get();*/
//        $row_news=User::po
//        return $row_news;


//        $users = User::popular()->active()->orderBy('created_at')->get();
        $users=WeChat::publish()->skip(0)->take(2)->get();
        return $users;
    }



}
