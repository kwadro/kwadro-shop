<?php

namespace App\Entity;

enum ProductAttributeDataType: string
{
    case Varchar = 'varchar';
    case Text = 'text';
    case Int = 'int';
    case Decimal = 'decimal';

    public function label(): string
    {
        return match ($this) {
            self::Varchar => 'Varchar',
            self::Text => 'Text',
            self::Int => 'Integer',
            self::Decimal => 'Decimal',
        };
    }
}
