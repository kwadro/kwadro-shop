<?php

namespace App\Entity;

enum RedirectType: string
{
    case Base = 'base';
    case Custom = 'custom';
}
