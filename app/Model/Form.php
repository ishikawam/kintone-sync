<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    public $timestamps = false;

    protected $table = 'form';

    protected $primaryKey = 'appId';

    protected $guarded = ['appId'];
}
