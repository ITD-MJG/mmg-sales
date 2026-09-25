<?php

namespace App\Models;

use App\Traits\HasCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Principal extends Model
{
    use HasCode, HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }

    protected $fillable = [
        'name',
        'initial',
        'description',
        'address',
        'is_active',
        'website',
        'principal_code',
        'principal_acc_code',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $codeColumn = 'principal_code';

    protected $codePrefix = 'PRN';

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
