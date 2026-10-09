<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly ProductRepository $productRepository,
        private readonly RequestStack $requestStack,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
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

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile('js/admin-product-clean-image.js');
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
        yield TextField::new('model', $this->translator->trans('admin.product.model', [], 'messages'));
        yield TextField::new('brand', $this->translator->trans('admin.product.brand', [], 'messages'));
        yield TextField::new('color', $this->translator->trans('admin.product.color', [], 'messages'))
            ->hideOnIndex();
        yield TextField::new('type', $this->translator->trans('admin.product.type', [], 'messages'))
            ->hideOnIndex();
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
        yield BooleanField::new('enabled', $this->translator->trans('admin.product.enabled', [], 'messages'))
            ->renderAsSwitch(false)
            ->formatValue(fn (mixed $value): string => $value
                ? $this->translator->trans('admin.product.enabled_yes', [], 'messages')
                : $this->translator->trans('admin.product.enabled_no', [], 'messages'));
        yield BooleanField::new('in_stock', $this->translator->trans('admin.product.in_stock', [], 'messages'));
        yield IntegerField::new('stock_qty', $this->translator->trans('admin.product.stock_qty', [], 'messages'));
        yield TextField::new('badge', $this->translator->trans('admin.product.badge', [], 'messages'))
            ->hideOnIndex();
        yield TextareaField::new('shortDescription', $this->translator->trans('admin.product.short_description', [], 'messages'))
            ->hideOnIndex();
        yield TextareaField::new('description', $this->translator->trans('admin.product.description', [], 'messages'))
            ->hideOnIndex()
            ->setNumOfRows(8)
            ->setFormTypeOption('attr', ['data-html-editor' => '1'])
            ->setHelp($this->translator->trans('admin.product.description_help', [], 'messages'));

        yield TextField::new('title', $this->translator->trans('admin.product.title', [], 'messages'))
            ->hideOnIndex();
        yield TextField::new('metaTitle', $this->translator->trans('admin.product.meta_title', [], 'messages'))
            ->hideOnIndex();
        yield TextareaField::new('metaDescription', $this->translator->trans('admin.product.meta_description', [], 'messages'))
            ->hideOnIndex()
            ->setNumOfRows(3);
        yield TextField::new('ogTitle', $this->translator->trans('admin.product.og_title', [], 'messages'))
            ->hideOnIndex();
        yield TextField::new('ogDescription', $this->translator->trans('admin.product.og_description', [], 'messages'))
            ->hideOnIndex();
        yield TextField::new('ogType', $this->translator->trans('admin.product.og_type', [], 'messages'))
            ->hideOnIndex()
            ->setHelp($this->translator->trans('admin.product.og_type_help', [], 'messages'));
        yield ImageField::new('ogImage', $this->translator->trans('admin.product.og_image', [], 'messages'))
            ->setBasePath('/uploads/images')
            ->setUploadDir('public/uploads/images')
            ->setRequired(false)
            ->hideOnIndex();

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
            ->setFormTypeOption('attr', ['data-html-editor' => '1'])
            ->setHelp($this->translator->trans('admin.product.features_help', [], 'messages'));

        $galleryUploadDir = 'public/uploads/products';
        $galleryBasePath = '/uploads/products';

        $product = $this->getContext()?->getEntity()?->getInstance();
        $productId = $product instanceof Product ? $product->getId() : null;
        $locale = (string) ($this->requestStack->getCurrentRequest()?->attributes->get('_locale') ?? 'uk');

        if ($productId !== null) {
            $urlTemplate = $this->generateUrl('admin_product_clean_image_generate', [
                '_locale' => $locale,
                'id' => $productId,
                'slot' => 999,
            ]);
            $urlTemplate = str_replace('/999', '/__SLOT__', $urlTemplate);
            $config = [
                'urlTemplate' => $urlTemplate,
                'labels' => [
                    'generate' => $this->translator->trans('admin.product.generate_clean_image', [], 'messages'),
                    'processing' => $this->translator->trans('admin.product.clean_image_processing', [], 'messages'),
                    'error' => $this->translator->trans('admin.product.clean_image_error', [], 'messages'),
                ],
            ];
            yield TextField::new('cleanImageTools')
                ->setLabel(false)
                ->onlyOnForms()
                ->onlyWhenUpdating()
                ->setFormType(HiddenType::class)
                ->setFormTypeOptions([
                    'mapped' => false,
                    'required' => false,
                    'attr' => [
                        'data-product-clean-config' => json_encode($config, \JSON_UNESCAPED_UNICODE),
                    ],
                ])
                ->hideOnIndex();
        }

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
            ->hideOnIndex();
        yield TextField::new('galleryAlt3', $this->translator->trans('admin.product.gallery_alt', ['%number%' => 3], 'messages'))
            ->setRequired(false)
            ->hideOnIndex();
        yield ImageField::new('galleryImage4', $this->translator->trans('admin.product.gallery_image', ['%number%' => 4], 'messages'))
            ->setBasePath($galleryBasePath)
            ->setUploadDir($galleryUploadDir)
            ->setRequired(false)
            ->hideOnIndex();
        yield TextField::new('galleryAlt4', $this->translator->trans('admin.product.gallery_alt', ['%number%' => 4], 'messages'))
            ->setRequired(false)
            ->hideOnIndex();
        yield ImageField::new('galleryImage5', $this->translator->trans('admin.product.gallery_image', ['%number%' => 5], 'messages'))
            ->setBasePath($galleryBasePath)
            ->setUploadDir($galleryUploadDir)
            ->setRequired(false)
            ->hideOnIndex()
            ->setHelp($this->translator->trans('admin.product.gallery_help', [], 'messages'));
        yield TextField::new('galleryAlt5', $this->translator->trans('admin.product.gallery_alt', ['%number%' => 5], 'messages'))
            ->setRequired(false)
            ->hideOnIndex();

        yield ImageField::new('cleanImage', $this->translator->trans('admin.product.clean_image', [], 'messages'))
            ->setBasePath($galleryBasePath)
            ->setUploadDir($galleryUploadDir)
            ->setRequired(false)
            ->hideOnIndex()
            ->setHelp($this->translator->trans('admin.product.clean_image_help', [], 'messages'));
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Product) {
            $entityInstance->rebuildNameFromModelBrand();
            $entityInstance->syncGalleryFromFormFields();
            $entityInstance->relocateGalleryFiles($this->projectDir);
            $entityInstance->relocateCleanImageFile($this->projectDir);
            $this->ensureUniqueSlug($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Product) {
            $entityInstance->rebuildNameFromModelBrand();
            $entityInstance->syncGalleryFromFormFields();
            $entityInstance->relocateGalleryFiles($this->projectDir);
            $entityInstance->relocateCleanImageFile($this->projectDir);
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
