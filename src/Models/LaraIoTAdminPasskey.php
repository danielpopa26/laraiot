<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $admin_id
 * @property string $name
 * @property string $credential_id
 * @property array<string, mixed> $credential
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class LaraIoTAdminPasskey extends Model
{
    protected $table = 'laraiot_admin_passkeys';

    protected $fillable = [
        'admin_id',
        'name',
        'credential_id',
        'credential',
        'last_used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credential' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LaraIoTAdmin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(
            LaraIoTAdmin::class,
            'admin_id',
        );
    }
}
