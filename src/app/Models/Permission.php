<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use SoftDeletes;

    public $table = "permissions";
  
    protected $fillable = [
        'name',
        'description'
    ];

    // Optional: Specify the date fields to cast
    protected $dates = ['deleted_at'];

    // Define the many-to-many relationship with roles
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}
