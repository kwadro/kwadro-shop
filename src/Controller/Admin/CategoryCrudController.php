<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class CategoryCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly CategoryRepository $categoryRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addCssFile('css/admin-category-tree.css')
            ->addJsFile('js/admin-category-tree.js');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_category_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_category', [], 'messages'))
            ->setDefaultSort(['level' => 'ASC', 'position' => 'ASC', 'name' => 'ASC'])
            ->setPageTitle(Crud::PAGE_INDEX, $this->translator->trans('admin.category.tree_title', [], 'messages'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel($this->translator->trans('admin.category.add_root', [], 'messages'));
            })
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->displayIf(static fn (Category $category): bool => !$category->isDefault());
            })
            ->add(Crud::PAGE_EDIT, Action::DELETE)
            ->update(Crud::PAGE_EDIT, Action::DELETE, function (Action $action) {
                return $action->displayIf(static fn (Category $category): bool => !$category->isDefault());
            });
    }

    public function index(AdminContext $context): KeyValueStore|Response
    {
        $this->categoryRepository->findOrCreateDefault();
        $tree = $this->categoryRepository->buildAdminTree();
        $selectedId = (int) ($this->requestStack->getCurrentRequest()?->query->get('selectedId') ?? 0);
        $selected = $selectedId > 0 ? $this->categoryRepository->find($selectedId) : $this->categoryRepository->findDefaultItem();

        $productCounts = $this->categoryRepository->countProductsGroupedByCategoryId();
        $selectedProductCount = $selected?->getId() !== null
            ? ($productCounts[$selected->getId()] ?? 0)
            : 0;

        $selectedId = $selected?->getId();

        return $this->render('admin/category/tree.html.twig', [
            'tree' => $tree,
            'selected' => $selected,
            'selectedProductCount' => $selectedProductCount,
            'urls' => [
                'addRoot' => $this->buildCategoryUrl(Action::NEW, null, ['parentId' => 0]),
                'addSubcategory' => $selectedId !== null
                    ? $this->buildCategoryUrl(Action::NEW, null, ['parentId' => $selectedId])
                    : null,
                'edit' => $selectedId !== null
                    ? $this->buildCategoryUrl(Action::EDIT, $selectedId)
                    : null,
                'delete' => $selectedId !== null && !$selected->isDefault()
                    ? $this->buildCategoryUrl(Action::DELETE, $selectedId)
                    : null,
                'selectBase' => $this->buildCategoryUrl(Action::INDEX),
            ],
        ]);
    }

    /**
     * @param array<string, scalar|null> $extra
     */
    private function buildCategoryUrl(string $action, ?int $entityId = null, array $extra = []): string
    {
        $generator = $this->adminUrlGenerator
            ->unsetAll()
            ->setController(self::class)
            ->setAction($action);

        if ($entityId !== null) {
            $generator->setEntityId($entityId);
        }

        foreach ($extra as $key => $value) {
            $generator->set($key, $value);
        }

        return $generator->generateUrl();
    }

    public function createEntity(string $entityFqcn): Category
    {
        $category = new Category();
        $request = $this->requestStack->getCurrentRequest();
        $parentId = (int) ($request?->query->get('parentId') ?? -1);

        if ($parentId === 0) {
            $category->setParent(null);

            return $category;
        }

        if ($parentId > 0) {
            $parent = $this->categoryRepository->find($parentId);
            if ($parent instanceof Category) {
                $category->setParent($parent);

                return $category;
            }
        }

        $category->setParent($this->categoryRepository->findOrCreateDefault());

        return $category;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', $this->translator->trans('admin.category.name', [], 'messages'));
        yield TextField::new('slug', $this->translator->trans('admin.category.slug', [], 'messages'));
        yield BooleanField::new('enabled', $this->translator->trans('admin.category.enabled', [], 'messages'));
        yield AssociationField::new('parent', $this->translator->trans('admin.category.parent', [], 'messages'))
            ->setRequired(false)
            ->setHelp($this->translator->trans('admin.category.parent_help', [], 'messages'));
        yield IntegerField::new('level', $this->translator->trans('admin.category.level', [], 'messages'))
            ->setFormTypeOption('disabled', true)
            ->setHelp($this->translator->trans('admin.category.level_help', [], 'messages'));
        yield IntegerField::new('position', $this->translator->trans('admin.category.position', [], 'messages'));
        yield TextField::new('metaTitle', $this->translator->trans('admin.category.meta_title', [], 'messages'))
            ->hideOnIndex();
        yield TextareaField::new('metaDescription', $this->translator->trans('admin.category.meta_description', [], 'messages'))
            ->hideOnIndex()
            ->setNumOfRows(3);
        yield AssociationField::new('children', $this->translator->trans('admin.category.children', [], 'messages'))
            ->onlyOnDetail();
        yield AssociationField::new('products', $this->translator->trans('admin.category.products', [], 'messages'))
            ->onlyOnDetail()
            ->setDisabled();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Category) {
            return;
        }

        $this->normalizeCategory($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Category) {
            return;
        }

        if ($entityInstance->isDefault()) {
            $entityInstance->setName(Category::DEFAULT_NAME);
            $entityInstance->setSlug(Category::DEFAULT_SLUG);
            $entityInstance->setParent(null);
            $entityInstance->setLevel(0);
        }

        $this->normalizeCategory($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Category && $entityInstance->isDefault()) {
            throw new BadRequestHttpException($this->translator->trans('admin.category.cannot_delete_default', [], 'messages'));
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $submitButtonName = $context->getRequest()->request->all()['ea']['newForm']['btn'] ?? null;
        $entityId = $context->getEntity()->getPrimaryKeyValue();

        if ($submitButtonName === Action::SAVE_AND_RETURN || $submitButtonName === null) {
            return $this->redirect($this->buildCategoryUrl(Action::INDEX, null, [
                'selectedId' => $entityId,
            ]));
        }

        return parent::getRedirectResponseAfterSave($context, $action);
    }

    private function normalizeCategory(Category $category): void
    {
        if ($category->getSlug() === '') {
            $category->setSlug($this->slugify($category->getName()));
        }

        $category->recalculateLevel();
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9а-яіїєґ\-_\s]+/u', '', $value) ?? '';
        $value = preg_replace('/[\s_]+/u', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'category';
    }
}
