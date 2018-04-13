<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    //table name..actually it is already assigned
    protected $table = 'Posts';
    //primary key
    public $primarykey = 'id';
    //timestamp
    public $timestamp = true;

    //model relationship
    public function user(){
        return $this->belongsTo('App\User');
    }
}
