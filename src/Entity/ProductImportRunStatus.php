<?php

namespace App\Entity;

enum ProductImportRunStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
}
