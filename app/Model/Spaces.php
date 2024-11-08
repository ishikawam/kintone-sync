<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Spaces extends Model
{
    public $timestamps = false;

    //    protected $primaryKey = 'id';
    protected $guarded = ['id'];
}
