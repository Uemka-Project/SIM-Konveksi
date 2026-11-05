<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $table = 'product_variants';

    protected $primaryKey = 'variant_id';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'size',
        'color',
        'sku',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(
            Material::class,
            'variant_materials',
            'variant_id',
            'material_id',
        )->withPivot('quantity_per_unit');
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'variant_id', 'variant_id');
    }
}
