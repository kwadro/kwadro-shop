<?php

namespace App\Controller\Admin;

use App\Entity\RequestList;
use App\Repository\RequestListRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

class RequestListCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RequestListRepository $requestListRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return RequestList::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_request_list_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_request_list', [], 'messages'))
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['ip', 'path'])
            ->setPaginatorPageSize(50)
            ->overrideTemplate('crud/index', 'admin/request_list/index.html.twig');
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
            ->add(TextFilter::new('ip'))
            ->add(TextFilter::new('path'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield DateTimeField::new('createdAt', $this->translator->trans('admin.request_list.created_at', [], 'messages'));
        yield TextField::new('ip', $this->translator->trans('admin.request_list.ip', [], 'messages'));
        yield TextField::new('path', $this->translator->trans('admin.request_list.path', [], 'messages'));
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if ($responseParameters->get('pageName') === Crud::PAGE_INDEX) {
            $responseParameters->set('uniqueIpsToday', $this->requestListRepository->countUniqueIpsToday());
            $responseParameters->set('requestsToday', $this->requestListRepository->countToday());
        }

        return $responseParameters;
    }
}
