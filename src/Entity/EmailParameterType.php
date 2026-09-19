<?php

namespace App\Entity;

enum EmailParameterType: string
{
    case Text = 'text';
    case Image = 'image';
}
