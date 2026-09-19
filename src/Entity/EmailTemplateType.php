<?php

namespace App\Entity;

enum EmailTemplateType: string
{
    case Html = 'html';
    case Text = 'text';
}
