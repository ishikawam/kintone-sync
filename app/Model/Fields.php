<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Fields extends Model
{
    protected $primaryKey = 'appId';
    protected $guarded = ['appId'];
    public $timestamps = false;
}
