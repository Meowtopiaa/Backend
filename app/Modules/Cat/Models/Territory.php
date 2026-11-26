<?php

namespace App\Modules\Cat\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Territory extends Model
{
    use HasUlids;

    protected $table = 'territories';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'area',
    ];
}
