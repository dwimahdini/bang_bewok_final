<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    protected $table = 'status';
    protected $fillable = ['nama_status'];
    protected $primaryKey = 'id';
    public $timestamps = false;
}
