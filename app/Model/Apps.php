<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Apps extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'appId';

    protected $guarded = ['appId'];
}
