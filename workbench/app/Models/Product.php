<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/** A demo model for the workbench's Products resource. */
class Product extends Model
{
    protected $guarded = [];

    protected $casts = ['active' => 'boolean', 'released_on' => 'date', 'price' => 'decimal:2'];
}
