<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMergeLog extends Model
{
    protected $fillable = ['master_contact_id', 'secondary_contact_id', 'changes', 'performed_by'];

    protected $casts = [
        'changes' => 'array',
    ];

    public function masterContact()
    {
        return $this->belongsTo(Contact::class, 'master_contact_id');
    }

    public function secondaryContact()
    {
        return $this->belongsTo(Contact::class, 'secondary_contact_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
