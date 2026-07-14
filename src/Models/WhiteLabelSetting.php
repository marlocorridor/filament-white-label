<?php

namespace MuazzamBuilds\FilamentWhiteLabel\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $panel_id
 * @property string $tenant_key
 * @property array<string, mixed> $data
 */
class WhiteLabelSetting extends Model
{
    protected $table = 'filament_white_label_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'panel_id',
        'tenant_key',
        'data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
