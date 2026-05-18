<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupervisorTeamMember extends Model
{
    protected $table = 'supervisor_team_members';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    public function scopeActive($query)
    {
        return $query->whereNull('end_date');
    }
}
