<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialCategoria extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'historial_categorias';

    protected $fillable = [
        'nomina_id', 'anio', 'categoria', 'fecha_categorizacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_categorizacion' => 'date',
            'created_at'           => 'datetime',
        ];
    }

    public function nomina(): BelongsTo
    {
        return $this->belongsTo(Nomina::class);
    }
}
