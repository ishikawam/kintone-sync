<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Fields extends Model
{
    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    public $timestamps = false;
}
