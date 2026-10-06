<?php

namespace App\Entity;

enum ProductImportMode: string
{
    case AddUpdate = 'add_update';
    case DeleteAddUpdate = 'delete_add_update';

    public function label(): string
    {
        return match ($this) {
            self::AddUpdate => 'Add/Update',
            self::DeleteAddUpdate => 'Delete Add/Update',
        };
    }
}
