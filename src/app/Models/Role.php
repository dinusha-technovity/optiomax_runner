<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'tenant_id'];

    // Optional: Specify the date fields to cast
    protected $dates = ['deleted_at'];

    // Define the many-to-many relationship with permissions
    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    // Define the many-to-many relationship with users
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
