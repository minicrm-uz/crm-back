<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'New';
    case Contacted = 'Contacted';
    case Qualified = 'Qualified';
    case Won = 'Won';
    case Lost = 'Lost';
}
