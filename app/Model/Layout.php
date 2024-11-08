<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Layout extends Model
{
    public $timestamps = false;

    protected $table = 'layout';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];
}
