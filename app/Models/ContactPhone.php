<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactPhone extends Model
{
    protected $fillable = ['contact_id', 'phone', 'is_primary'];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
