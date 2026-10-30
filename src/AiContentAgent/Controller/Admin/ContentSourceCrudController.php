<?php

namespace App\AiContentAgent\Controller\Admin;

use App\AiContentAgent\Entity\ContentSource;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class ContentSourceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ContentSource::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Source')
            ->setEntityLabelInPlural('Source history')
            ->setDefaultSort(['discoveredAt' => 'DESC'])
            ->setSearchFields(['url', 'title', 'topicKey'])
            ->setPaginatorPageSize(50);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield DateTimeField::new('discoveredAt', 'Discovered');
        yield DateTimeField::new('lastSeenAt', 'Last seen')->hideOnIndex();
        yield TextField::new('title');
        yield UrlField::new('url');
        yield TextField::new('topicKey', 'Topic key')->hideOnIndex();
        yield TextareaField::new('excerpt')->hideOnIndex();
    }
}
