<?php
/**
 * Created by PhpStorm.
 * User: ubuntu
 * Date: 16-9-1
 * Time: 下午4:52
 */
@extends('app')
@section('content')
    <h1>Articles</h1>
    <hr>
    @foreach($articles as $article)
        <h2>{{$article->title}}</h2>
        <article>

        </article>
    @endforeach
@stop