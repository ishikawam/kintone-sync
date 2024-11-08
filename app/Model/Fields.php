<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Fields extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'id';

    protected $guarded = ['id'];
}
