<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    protected $table = 'employees';

    protected $primaryKey = 'employee_id';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'phone',
        'position',
        'active_status',
    ];

    protected function casts(): array
    {
        return [
            'active_status' => 'boolean',
        ];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'employee_id', 'employee_id');
    }

    public function productionProgress(): HasMany
    {
        return $this->hasMany(ProductionProgress::class, 'employee_id', 'employee_id');
    }
}
