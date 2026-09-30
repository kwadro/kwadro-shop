<?php

namespace App\Controller\Admin;

use App\Entity\BlogCategory;
use App\Repository\BlogCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

class BlogCategoryCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly BlogCategoryRepository $categoryRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return BlogCategory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_blog_category_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_blog_category', [], 'messages'))
            ->setDefaultSort(['level' => 'ASC', 'position' => 'ASC', 'name' => 'ASC'])
            ->setFormOptions(['csrf_protection' => false]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('site', $this->translator->trans('admin.blog.site', [], 'messages'))
            ->setRequired(true);
        yield AssociationField::new('locale', $this->translator->trans('admin.blog.locale', [], 'messages'))
            ->setRequired(true);
        yield TextField::new('name', $this->translator->trans('admin.blog_category.name', [], 'messages'));
        yield TextField::new('slug', $this->translator->trans('admin.blog_category.slug', [], 'messages'))
            ->setHelp($this->translator->trans('admin.blog.slug_help', [], 'messages'));
        yield AssociationField::new('parent', $this->translator->trans('admin.blog_category.parent', [], 'messages'))
            ->setRequired(false)
            ->setHelp($this->translator->trans('admin.blog_category.parent_help', [], 'messages'));
        yield IntegerField::new('position', $this->translator->trans('admin.blog_category.position', [], 'messages'));
        yield IntegerField::new('level', $this->translator->trans('admin.blog_category.level', [], 'messages'))
            ->hideOnForm();
        yield BooleanField::new('enabled', $this->translator->trans('admin.blog.enabled', [], 'messages'));
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof BlogCategory) {
            $this->ensureUniqueSlug($entityInstance);
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof BlogCategory) {
            $this->ensureUniqueSlug($entityInstance);
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function ensureUniqueSlug(BlogCategory $category): void
    {
        if ($category->getSlug() === '' && $category->getName() !== '') {
            $category->setSlug($this->slugify($category->getName()));
        }

        $site = $category->getSite();
        $locale = $category->getLocale();
        if ($site === null || $locale === null || $category->getSlug() === '') {
            return;
        }

        $base = $category->getSlug();
        $slug = $base;
        $suffix = 1;
        while ($this->categoryRepository->slugExists($site, $locale, $slug, $category->getId())) {
            $slug = $base.'-'.$suffix;
            ++$suffix;
        }
        $category->setSlug($slug);
    }

    private function slugify(string $value): string
    {
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g', 'д' => 'd', 'е' => 'e', 'є' => 'ye',
            'ж' => 'zh', 'з' => 'z', 'и' => 'y', 'і' => 'i', 'ї' => 'yi', 'й' => 'y', 'к' => 'k', 'л' => 'l',
            'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ь' => '', 'ю' => 'yu',
            'я' => 'ya', 'ы' => 'y', 'э' => 'e', 'ъ' => '',
        ];
        $value = mb_strtolower(trim($value));
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';

        return trim($value, '-_') ?: 'category';
    }
}
