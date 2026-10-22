<?php

namespace App\Models;

use App\Services\ResourceCodeGenerator;
use App\Traits\HasCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Product extends Model
{
    use HasCode, HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }

    protected $fillable = [
        'name',
        'category',
        'description',
        'internal_code',
        'unit_price',
        'ecatalog_price',
        'unit_of_measure',
        'is_active',
        'principal_id',
    ];

    protected $codeColumn = 'product_code';

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'ecatalog_price' => 'integer',
        ];
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(Principal::class);
    }

    public function generateCode(): string
    {
        $principal = $this->principal ?? Principal::find($this->principal_id);
        $initial = $principal?->initial ?? 'XX';

        $generator = app(ResourceCodeGenerator::class);
        $sequence = $generator->getNextProductSequence($initial);

        return sprintf('%s-TD-%05d', strtoupper($initial), $sequence);
    }
}
