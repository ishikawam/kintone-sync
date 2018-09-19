<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Layout extends Model
{
    protected $table = 'layout';
    protected $primaryKey = 'id';
    protected $guarded = ['id'];
    public $timestamps = false;
}
