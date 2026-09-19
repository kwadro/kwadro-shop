<?php

namespace App\Controller\Admin;

use App\Entity\BankAccount;
use App\Entity\Locale;
use App\Entity\Site;
use App\Repository\BankAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

class BankAccountCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly BankAccountRepository $bankAccountRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return BankAccount::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_bank_account_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_bank_account', [], 'messages'))
            ->setDefaultSort(['site' => 'ASC', 'locale' => 'ASC', 'title' => 'ASC'])
            ->setSearchFields(['title', 'recipient', 'iban', 'bank_name', 'edrpou'])
            ->setFormOptions([
                'csrf_protection' => false,
            ]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('site'))
            ->add(EntityFilter::new('locale'));
    }

    public function createEntity(string $entityFqcn): object
    {
        $entity = new BankAccount();

        $site = $this->container->get('doctrine')->getRepository(Site::class)->findOneBy([], ['id' => 'ASC']);
        if ($site instanceof Site) {
            $entity->setSite($site);
        }

        $locale = $this->container->get('doctrine')->getRepository(Locale::class)->findOneBy(['code' => 'uk'])
            ?? $this->container->get('doctrine')->getRepository(Locale::class)->findOneBy([], ['id' => 'ASC']);
        if ($locale instanceof Locale) {
            $entity->setLocale($locale);
        }

        return $entity;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('site', $this->translator->trans('admin.bank_account.site', [], 'messages'))
            ->setRequired(true);
        yield AssociationField::new('locale', $this->translator->trans('admin.bank_account.locale', [], 'messages'))
            ->setRequired(true)
            ->setFormTypeOption('choice_label', static fn (Locale $locale): string => sprintf(
                '%s (%s)',
                (string) ($locale->getName() ?? $locale->getCode()),
                (string) ($locale->getCode() ?? ''),
            ));
        yield TextField::new('title', $this->translator->trans('admin.bank_account.title', [], 'messages'))
            ->setHelp($this->translator->trans('admin.bank_account.title_help', [], 'messages'));
        yield TextField::new('recipient', $this->translator->trans('admin.bank_account.recipient', [], 'messages'));
        yield TextField::new('edrpou', $this->translator->trans('admin.bank_account.edrpou', [], 'messages'));
        yield TextField::new('bankName', $this->translator->trans('admin.bank_account.bank_name', [], 'messages'));
        yield TextField::new('iban', $this->translator->trans('admin.bank_account.iban', [], 'messages'))
            ->setHelp($this->translator->trans('admin.bank_account.iban_help', [], 'messages'));
        yield BooleanField::new('isDefault', $this->translator->trans('admin.bank_account.is_default', [], 'messages'))
            ->renderAsSwitch(false);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->syncDefaultFlag($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->syncDefaultFlag($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function syncDefaultFlag(object $entityInstance): void
    {
        if (!$entityInstance instanceof BankAccount || !$entityInstance->isDefault()) {
            return;
        }

        $this->bankAccountRepository->clearDefaultExcept($entityInstance);
    }
}
