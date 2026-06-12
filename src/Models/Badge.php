<?php

namespace Convoro\Ext\Badges\Models;

use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'threshold' => 'integer',
        'position' => 'integer',
    ];
}
