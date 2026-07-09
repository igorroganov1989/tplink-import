<?php

declare(strict_types=1);

namespace Yd\TplinkImport\Domain\Enum;

enum ImportItemStatus: string
{
    case New = 'new';
    case Updated = 'updated';
    case Unchanged = 'unchanged';
    case Missing = 'missing';
    case Error = 'error';
}
