<?php

namespace App\Models;

use App\Models\Traits\FormatsDatesTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gateway extends Model
{
    use HasFactory, FormatsDatesTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'is_active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    /**
     * Get all transactions of the gateway.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
