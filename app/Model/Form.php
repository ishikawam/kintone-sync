<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    protected $table = 'form';

    protected $primaryKey = 'appId';

    protected $guarded = ['appId'];

    public $timestamps = false;
}
