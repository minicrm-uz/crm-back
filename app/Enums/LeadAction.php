<?php

namespace App\Enums;

enum LeadAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case StatusChanged = 'status_changed';
    case Deleted = 'deleted';
}
