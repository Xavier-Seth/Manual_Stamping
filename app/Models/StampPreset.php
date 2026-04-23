<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StampPreset extends Model
{
    protected $fillable = [
        'name',
        'description',
        'master_stamps',
        'controlled_stamps',
        'uncontrolled_stamps',
        'esign',
        'is_active',
    ];

    protected $casts = [
        'master_stamps' => 'array',
        'controlled_stamps' => 'array',
        'uncontrolled_stamps' => 'array',
        'esign' => 'array',
        'is_active' => 'boolean',
    ];
}