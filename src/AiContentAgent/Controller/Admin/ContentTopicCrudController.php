<?php

namespace App\AiContentAgent\Controller\Admin;

use App\AiContentAgent\Entity\ContentTopic;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ContentTopicCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ContentTopic::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Topic')
            ->setEntityLabelInPlural('Topics')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['title', 'topicKey', 'status'])
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
        yield TextField::new('title');
        yield TextField::new('status');
        yield IntegerField::new('sourceCount', 'Sources');
        yield AssociationField::new('blogArticle', 'Article');
        yield DateTimeField::new('processedAt', 'Processed');
        yield TextField::new('topicKey', 'Topic key')->hideOnIndex();
    }
}
