<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Enums;

enum ConditionType: string
{
    case Ownership = 'ownership';
    case Team = 'team';
    case Department = 'department';
    case Status = 'status';
    case Amount = 'amount';
    case TimeWindow = 'time_window';
    case IpRange = 'ip_range';
    case Attribute = 'attribute';
    case Custom = 'custom';
}
