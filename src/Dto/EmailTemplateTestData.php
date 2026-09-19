<?php

namespace App\Dto;

use App\Entity\EmailSender;
use App\Entity\EmailTemplate;
use App\Entity\Order;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

final class EmailTemplateTestData
{
    #[Assert\NotNull]
    public ?EmailTemplate $template = null;

    #[Assert\NotNull]
    public ?EmailSender $sender = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $recipient = '';

    public ?Order $order = null;

    public ?User $user = null;
}
