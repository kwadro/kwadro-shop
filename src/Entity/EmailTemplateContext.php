<?php

namespace App\Entity;

enum EmailTemplateContext: string
{
    case Order = 'order';
    case User = 'user';
}
