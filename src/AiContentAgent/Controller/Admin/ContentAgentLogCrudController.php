<?php

namespace App\AiContentAgent\Controller\Admin;

use App\AiContentAgent\Entity\ContentAgentLog;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class ContentAgentLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ContentAgentLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Agent log entry')
            ->setEntityLabelInPlural('Agent log')
            ->setDefaultSort(['created_at' => 'DESC'])
            ->setSearchFields(['action', 'message', 'level'])
            ->setPaginatorPageSize(50);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('level')->setChoices([
                'Info' => ContentAgentLog::LEVEL_INFO,
                'Warning' => ContentAgentLog::LEVEL_WARNING,
                'Error' => ContentAgentLog::LEVEL_ERROR,
            ]))
            ->add(TextFilter::new('action'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield DateTimeField::new('created_at', 'Time');
        yield TextField::new('level');
        yield TextField::new('action');
        yield TextField::new('message');
        yield IntegerField::new('queueItemId', 'Queue item')->hideOnIndex();
        yield TextareaField::new('context')
            ->onlyOnDetail()
            ->formatValue(static function (mixed $value): string {
                if (!is_array($value) || $value === []) {
                    return '—';
                }

                return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '—';
            });
    }
}
