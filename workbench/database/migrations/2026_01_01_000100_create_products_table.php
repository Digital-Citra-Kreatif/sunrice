<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status')->default('draft');
            $table->boolean('active')->default(false);
            $table->date('released_on')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
};
