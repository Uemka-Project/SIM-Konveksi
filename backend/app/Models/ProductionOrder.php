<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrder extends Model
{
    protected $table = 'production_orders';

    protected $primaryKey = 'production_order_id';

    public $timestamps = false;

    protected $fillable = [
        'order_detail_id',
        'planned_quantity',
        'status',
        'start_date',
        'target_date',
    ];

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'integer',
            'start_date' => 'date',
            'target_date' => 'date',
        ];
    }

    public function orderDetail(): BelongsTo
    {
        return $this->belongsTo(OrderDetail::class, 'order_detail_id', 'order_detail_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(ProductionProgress::class, 'production_order_id', 'production_order_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'production_order_id', 'production_order_id');
    }
}
