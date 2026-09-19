<?php
namespace App\Controller\Admin;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use App\Entity\SeoSetting;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use Symfony\Contracts\Translation\TranslatorInterface;



class SeoSettingCrudController extends AbstractCrudController
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }
    public static function getEntityFqcn(): string { return SeoSetting::class; }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('site'),
            AssociationField::new('translations')->setFormTypeOption('by_reference', false),
        ];
    }


    public function configureCrud(Crud $crud): Crud
    {
         $manage = $this->translator->trans('grud.manage', [], 'messages');
         $edit = $this->translator->trans('grud.edit', [], 'messages');
         $createNew = $this->translator->trans('grud.create_new', [], 'messages');
         $linkName = $this->translator->trans('menu.link_seosetting_single', [], 'messages');
         return $crud
            ->setFormThemes([
               '@EasyAdmin/crud/form_theme.html.twig',
               'admin/fields.html.twig'
            ])
            ->setPageTitle('index', sprintf('%s %s',$manage,$linkName)) // For the list view
            ->setPageTitle('edit', sprintf('%s %s',$edit,$linkName).' id : %entity_id%') // For the edit form
            ->setPageTitle('new', sprintf('%s %s',$createNew,$linkName))
            ->setDefaultSort(['created_at' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
          $export = Action::new('exportCsv', $this->translator->trans('menu.link_export_csv', [], 'messages'))
                      ->linkToRoute('admin_export_csv', ['entity' => 'SeoSetting'])
                      ->createAsGlobalAction()
                      ->addCssClass('btn btn-secondary')
                      ->setIcon('fa fa-file-csv');
          $import = Action::new('import', $this->translator->trans('menu.link_import_csv', [], 'messages'))
                       ->linkToRoute('admin_import', ['entity' => 'SeoSetting'])
                       ->createAsGlobalAction()
                       ->addCssClass('btn btn-secondary')
                       ->setIcon('fa fa-upload');
          $addNew = $this->translator->trans('menu.link_new', [], 'messages');
          $linkName = $this->translator->trans('menu.link_seosetting_single', [], 'messages');
          return $actions
             ->add(Crud::PAGE_INDEX, $export)
             ->add(Crud::PAGE_INDEX, $import)
             ->update(Crud::PAGE_INDEX, Action::NEW,
                         fn (Action $action) =>
                             $action->setLabel(sprintf('%s %s',$addNew,$linkName))
                     );
    //    return $actions
    //        ->setPermission(Action::DETAIL, 'ROLE_USER');
    }
}
