<?php

namespace App\Entity;

enum EmailLogStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
