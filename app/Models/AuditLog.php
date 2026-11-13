<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class AuditLog extends Model
{
    /** @use HasFactory<\Database\Factories\AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'actor_name', 'action', 'subject_type', 'subject_id', 'subject_label', 'before_values', 'after_values', 'metadata'];

    protected function casts(): array
    {
        return ['before_values' => 'array', 'after_values' => 'array', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Audit history cannot be edited.');
        });
        static::deleting(function (): never {
            throw new LogicException('Audit history cannot be deleted.');
        });
    }
}
