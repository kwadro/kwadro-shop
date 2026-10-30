<?php

namespace App\AiContentAgent\Controller\Admin;

use App\AiContentAgent\Entity\PublicationQueueItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PublicationQueueCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PublicationQueueItem::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Publication')
            ->setEntityLabelInPlural('Publication queue')
            ->setDefaultSort(['scheduledAt' => 'ASC'])
            ->setSearchFields(['slug', 'status'])
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
        yield AssociationField::new('topic');
        yield TextField::new('status');
        yield DateTimeField::new('scheduledAt', 'Scheduled');
        yield TextField::new('slug');
        yield IntegerField::new('attemptCount', 'Attempts');
        yield AssociationField::new('blogArticle', 'Article')->hideOnIndex();
        yield DateTimeField::new('publishedAt', 'Published')->hideOnIndex();
        yield DateTimeField::new('notifiedAt', 'Notified')->hideOnIndex();
        yield TextareaField::new('lastError', 'Last error')->hideOnIndex();
    }
}
