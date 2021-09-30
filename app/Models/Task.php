<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    public $timestamps = true;

    protected $fillable = [
        'name',
        'decription',
        'start_date',
        'end_date',
        'status',
        'project_id',
        'created_by',
        'updated_by',
        'updated_at',
        'created_at'
       
    ];
    protected $table = 'tasks';

    protected function team(){
        return $this->belongsToMany(User::class,'h_resources_tasks','task_id','resource_id');
    }

    protected function resources(){
        return $this->belongsToMany(NonHumanResources::class,'nh_resources_tasks','task_id','resource_id');
    }
}
