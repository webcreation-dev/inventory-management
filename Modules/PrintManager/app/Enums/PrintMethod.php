<?php

namespace Modules\PrintManager\Enums;

enum PrintMethod: string
{
    case Browser = 'browser';
    case QzTray = 'qz_tray';
    case LocalAgent = 'local_agent';

    public function label(): string
    {
        return match ($this) {
            self::Browser => 'Browser Print',
            self::QzTray => 'QZ Tray',
            self::LocalAgent => 'Local Print Agent',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
