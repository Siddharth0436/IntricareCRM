<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;


class CustomField extends Model
{
protected $fillable = ['label','name','type','meta','required'];


protected $casts = [
'meta' => 'array',
'required' => 'boolean',
];
}