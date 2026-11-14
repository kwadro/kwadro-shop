<?php

namespace App\Controller\Admin;

use App\Entity\BotUserAgentMarker;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

class BotUserAgentMarkerCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return BotUserAgentMarker::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_bot_user_agent_marker_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_bot_user_agent_marker', [], 'messages'))
            ->setDefaultSort(['marker' => 'ASC'])
            ->setSearchFields(['marker', 'note'])
            ->setPaginatorPageSize(50)
            ->setFormOptions(['csrf_protection' => false])
            ->setHelp('index', $this->translator->trans('admin.bot_user_agent_marker.help', [], 'messages'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('marker', $this->translator->trans('admin.bot_user_agent_marker.marker', [], 'messages'))
            ->setHelp($this->translator->trans('admin.bot_user_agent_marker.marker_help', [], 'messages'));
        yield TextField::new('note', $this->translator->trans('admin.bot_user_agent_marker.note', [], 'messages'))
            ->setRequired(false);
        yield BooleanField::new('isActive', $this->translator->trans('admin.bot_user_agent_marker.is_active', [], 'messages'));
        yield DateTimeField::new('created_at', $this->translator->trans('admin.bot_user_agent_marker.created_at', [], 'messages'))
            ->hideOnForm();
        yield DateTimeField::new('updated_at', $this->translator->trans('admin.bot_user_agent_marker.updated_at', [], 'messages'))
            ->hideOnForm()
            ->hideOnIndex();
    }
}
