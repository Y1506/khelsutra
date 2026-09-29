<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;

class Sport extends Model
{
    use BelongsToOrganization;

    protected $table = 'sports';
    protected $guarded = ['id'];
}
