<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComisionIntegrante extends Model
{
    use HasUuids;

    protected $fillable = ['comision_cca_id', 'nomina_id'];

    public function comision(): BelongsTo
    {
        return $this->belongsTo(ComisionCca::class, 'comision_cca_id');
    }

    public function nomina(): BelongsTo
    {
        return $this->belongsTo(Nomina::class);
    }
}
