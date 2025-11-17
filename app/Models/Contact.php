<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Contact extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'gender', 'profile_image', 'additional_file', 'is_active', 'merged_to'];

    public function customValues()
    {
        return $this->hasMany(ContactCustomValue::class);
    }

    public function emails()
    {
        return $this->hasMany(ContactEmail::class);
    }

    public function phones()
    {
        return $this->hasMany(ContactPhone::class);
    }

    public function mergedTo()
    {
        return $this->belongsTo(Contact::class, 'merged_to');
    }

    public function mergeLogsAsMaster()
    {
        return $this->hasMany(ContactMergeLog::class, 'master_contact_id');
    }
}
