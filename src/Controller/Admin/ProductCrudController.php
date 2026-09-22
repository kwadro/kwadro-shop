<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Repository\ProductRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly ProductRepository $productRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_product_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_product', [], 'messages'))
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewAllOffers = Action::new('viewAllOffers', $this->translator->trans('admin.product.view_all_offers', [], 'messages'))
            ->linkToUrl(function (Product $product): string {
                return $this->adminUrlGenerator
                    ->setController(ProductOfferCrudController::class)
                    ->set('filters[product][comparison]', '=')
                    ->set('filters[product][value]', (string) $product->getId())
                    ->generateUrl();
            })
            ->displayIf(static fn (?Product $product): bool => $product?->getId() !== null && $product->getOffersCount() > 0)
            ->addCssClass('btn btn-secondary')
            ->setIcon('fa fa-list');

        return $actions
            ->add(Crud::PAGE_EDIT, $viewAllOffers)
            ->add(Crud::PAGE_DETAIL, $viewAllOffers);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', $this->translator->trans('admin.product.name', [], 'messages'));
        yield TextField::new('sku', $this->translator->trans('admin.product.sku', [], 'messages'));
        yield TextField::new('slug', $this->translator->trans('admin.product.slug', [], 'messages'))
            ->setHelp($this->translator->trans('admin.product.slug_help', [], 'messages'));
        yield AssociationField::new('categories', $this->translator->trans('admin.product.categories', [], 'messages'))
            ->setFormTypeOption('by_reference', false)
            ->setRequired(false)
            ->formatValue(static fn ($value, Product $product): string => $product->getCategoriesLabel() !== ''
                ? $product->getCategoriesLabel()
                : '—');
        yield NumberField::new('weight', $this->translator->trans('admin.product.weight', [], 'messages'))
            ->setNumDecimals(3)
            ->hideOnIndex();
        yield NumberField::new('packageHeight', $this->translator->trans('admin.product.package_height', [], 'messages'))
            ->setNumDecimals(2)
            ->hideOnIndex();
        yield NumberField::new('packageWidth', $this->translator->trans('admin.product.package_width', [], 'messages'))
            ->setNumDecimals(2)
            ->hideOnIndex();
        yield NumberField::new('packageLength', $this->translator->trans('admin.product.package_length', [], 'messages'))
            ->setNumDecimals(2)
            ->hideOnIndex();
        yield BooleanField::new('in_stock', $this->translator->trans('admin.product.in_stock', [], 'messages'));
        yield IntegerField::new('stock_qty', $this->translator->trans('admin.product.stock_qty', [], 'messages'));
        yield TextField::new('badge', $this->translator->trans('admin.product.badge', [], 'messages'))
            ->hideOnIndex();
        yield TextareaField::new('shortDescription', $this->translator->trans('admin.product.short_description', [], 'messages'))
            ->hideOnIndex();
        yield TextareaField::new('description', $this->translator->trans('admin.product.description', [], 'messages'))
            ->hideOnIndex()
            ->setNumOfRows(8)
            ->setHelp($this->translator->trans('admin.product.description_help', [], 'messages'));

        yield TextField::new('lowestOfferSummary', $this->translator->trans('admin.product.lowest_offer', [], 'messages'))
            ->onlyOnForms()
            ->setDisabled()
            ->formatValue(fn (?string $value, Product $product): string => $product->hasOffers()
                ? (string) $value
                : $this->translator->trans('admin.product.no_price', [], 'messages'))
            ->setHelp($this->translator->trans('admin.product.lowest_offer_help', [], 'messages'));
        yield TextField::new('lowestOfferSummary', $this->translator->trans('admin.product.lowest_offer', [], 'messages'))
            ->onlyOnIndex()
            ->formatValue(fn (?string $value, Product $product): string => $product->hasOffers()
                ? (string) $value
                : $this->translator->trans('admin.product.no_price', [], 'messages'));
        yield BooleanField::new('availableForSale', $this->translator->trans('admin.product.available_for_sale', [], 'messages'))
            ->onlyOnIndex()
            ->renderAsSwitch(false)
            ->formatValue(fn (mixed $value, Product $product): bool => $product->isAvailableForSale());
        yield IntegerField::new('offersCount', $this->translator->trans('admin.product.offers_count', [], 'messages'))
            ->onlyOnIndex();
        yield CollectionField::new('offers', $this->translator->trans('admin.product.offers', [], 'messages'))
            ->useEntryCrudForm(ProductOfferCrudController::class)
            ->allowAdd()
            ->allowDelete()
            ->setEntryIsComplex()
            ->renderExpanded()
            ->onlyOnForms()
            ->onlyWhenUpdating();
        yield CollectionField::new('offers', $this->translator->trans('admin.product.offers', [], 'messages'))
            ->onlyOnDetail()
            ->useEntryCrudForm(ProductOfferCrudController::class)
            ->allowAdd(false)
            ->allowDelete(false);

        yield TextareaField::new('featuresText', $this->translator->trans('admin.product.features', [], 'messages'))
            ->hideOnIndex()
            ->setNumOfRows(6)
            ->setHelp($this->translator->trans('admin.product.features_help', [], 'messages'));

        $galleryUploadDir = 'public/uploads/products';
        $galleryBasePath = '/uploads/products';

        yield ImageField::new('galleryImage1', $this->translator->trans('admin.product.gallery_image', ['%number%' => 1], 'messages'))
            ->setBasePath($galleryBasePath)
            ->setUploadDir($galleryUploadDir)
            ->setRequired(false)
            ->hideOnIndex();
        yield TextField::new('galleryAlt1', $this->translator->trans('admin.product.gallery_alt', ['%number%' => 1], 'messages'))
            ->setRequired(false)
            ->hideOnIndex();
        yield ImageField::new('galleryImage2', $this->translator->trans('admin.product.gallery_image', ['%number%' => 2], 'messages'))
            ->setBasePath($galleryBasePath)
            ->setUploadDir($galleryUploadDir)
            ->setRequired(false)
            ->hideOnIndex();
        yield TextField::new('galleryAlt2', $this->translator->trans('admin.product.gallery_alt', ['%number%' => 2], 'messages'))
            ->setRequired(false)
            ->hideOnIndex();
        yield ImageField::new('galleryImage3', $this->translator->trans('admin.product.gallery_image', ['%number%' => 3], 'messages'))
            ->setBasePath($galleryBasePath)
            ->setUploadDir($galleryUploadDir)
            ->setRequired(false)
            ->hideOnIndex()
            ->setHelp($this->translator->trans('admin.product.gallery_help', [], 'messages'));
        yield TextField::new('galleryAlt3', $this->translator->trans('admin.product.gallery_alt', ['%number%' => 3], 'messages'))
            ->setRequired(false)
            ->hideOnIndex();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Product) {
            $entityInstance->syncGalleryFromFormFields();
            $this->ensureUniqueSlug($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Product) {
            $entityInstance->syncGalleryFromFormFields();
            $this->ensureUniqueSlug($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function ensureUniqueSlug(Product $product): void
    {
        $product->ensureSlug();
        $base = $product->getSlug();
        $slug = $base;
        $suffix = 1;

        while ($this->productRepository->slugExists($slug, $product->getId())) {
            $slug = $base . '-' . $suffix;
            ++$suffix;
        }

        $product->setSlug($slug);
    }
}
