<?php

namespace App\Controller\Admin;

use App\Entity\MailboxMessage;

class MailboxSentMessageCrudController extends MailboxMessageCrudController
{
    protected function listFolder(): string
    {
        return MailboxMessage::FOLDER_SENT;
    }
}
