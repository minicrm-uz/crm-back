<?php

namespace App\Enums;

enum LeadSource: string
{
    case Website = 'Website';
    case Referral = 'Referral';
    case Social = 'Social';
    case ColdCall = 'Cold Call';
    case Event = 'Event';
    case Other = 'Other';
}
