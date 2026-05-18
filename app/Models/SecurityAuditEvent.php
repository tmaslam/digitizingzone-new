<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityAuditEvent extends Model
{
    protected $table = 'security_audit_events';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'details_json' => 'array',
        ];
    }
}
