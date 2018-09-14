<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Layout extends Model
{
    protected $table = 'layout';
    protected $primaryKey = 'appId';
    protected $guarded = ['appId'];
    public $timestamps = false;
}
