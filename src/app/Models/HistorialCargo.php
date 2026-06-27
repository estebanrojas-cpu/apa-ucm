<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialCargo extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'historial_cargos';

    protected $fillable = [
        'user_id', 'periodo_id', 'facultad_id', 'cargo', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public function facultad(): BelongsTo
    {
        return $this->belongsTo(Facultad::class);
    }
}
