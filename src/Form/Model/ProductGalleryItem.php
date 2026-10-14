<?php

namespace App\Form\Model;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class ProductGalleryItem
{
    public ?string $path = null;

    public ?UploadedFile $file = null;

    #[Assert\Length(max: 255)]
    public string $alt = '';
}
