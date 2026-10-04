<?php

namespace App\Controller\Admin;

use App\Entity\BlogArticle;
use App\Repository\BlogArticleRepository;
use App\Service\Blog\BlogFacebookPostFormatter;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class BlogArticleCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly BlogArticleRepository $articleRepository,
        private readonly BlogFacebookPostFormatter $facebookPostFormatter,
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $defaultUri,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return BlogArticle::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('menu.link_blog_article_single', [], 'messages'))
            ->setEntityLabelInPlural($this->translator->trans('menu.link_blog_article', [], 'messages'))
            ->setDefaultSort(['publishedAt' => 'DESC', 'id' => 'DESC'])
            ->setFormOptions(['csrf_protection' => false]);
    }

    public function configureActions(Actions $actions): Actions
    {
        $facebook = Action::new('facebookDraft', $this->translator->trans('admin.blog_article.facebook_draft', [], 'messages'))
            ->linkToCrudAction('facebookDraft')
            ->setIcon('fa fa-facebook');

        return $actions
            ->add(Crud::PAGE_INDEX, $facebook)
            ->add(Crud::PAGE_DETAIL, $facebook)
            ->add(Crud::PAGE_EDIT, $facebook);
    }

    #[AdminRoute('/{entityId:article.id}/facebook-draft', name: 'facebook_draft')]
    public function facebookDraft(BlogArticle $article, Request $request): Response
    {
        $baseUrl = $this->resolveBaseUrl($article);
        $regenerate = $request->query->getBoolean('regenerate');

        if ($regenerate || $article->getFacebookDraft() === null || $article->getFacebookDraft() === '') {
            $draft = $this->facebookPostFormatter->format($article, $baseUrl);
            $article->setFacebookDraft($draft);
            $this->em->flush();
            if ($regenerate) {
                $this->addFlash('success', $this->translator->trans('admin.blog_article.facebook_regenerated', [], 'messages'));
            }
        }

        $articleUrl = $this->facebookPostFormatter->resolveArticleUrl($article, $baseUrl);
        $regenerateUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction('facebookDraft')
            ->setEntityId($article->getId())
            ->set('regenerate', 1)
            ->generateUrl();

        return $this->render('admin/blog/facebook_draft.html.twig', [
            'article' => $article,
            'draft' => (string) $article->getFacebookDraft(),
            'articleUrl' => $articleUrl,
            'regenerateUrl' => $regenerateUrl,
            'editUrl' => $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($article->getId())
                ->generateUrl(),
        ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('site', $this->translator->trans('admin.blog.site', [], 'messages'))
            ->setRequired(true);
        yield AssociationField::new('locale', $this->translator->trans('admin.blog.locale', [], 'messages'))
            ->setRequired(true);
        yield TextField::new('title', $this->translator->trans('admin.blog_article.title', [], 'messages'));
        yield TextField::new('slug', $this->translator->trans('admin.blog_article.slug', [], 'messages'))
            ->setHelp($this->translator->trans('admin.blog.slug_help', [], 'messages'));
        yield TextField::new('metaTitle', $this->translator->trans('admin.blog_article.meta_title', [], 'messages'))
            ->hideOnIndex();
        yield TextareaField::new('metaDescription', $this->translator->trans('admin.blog_article.meta_description', [], 'messages'))
            ->hideOnIndex()
            ->setNumOfRows(3);
        yield TextField::new('ogTitle', $this->translator->trans('admin.blog_article.og_title', [], 'messages'))
            ->hideOnIndex();
        yield TextareaField::new('ogDescription', $this->translator->trans('admin.blog_article.og_description', [], 'messages'))
            ->hideOnIndex()
            ->setNumOfRows(3);
        yield TextField::new('ogType', $this->translator->trans('admin.blog_article.og_type', [], 'messages'))
            ->setHelp($this->translator->trans('admin.blog_article.og_type_help', [], 'messages'))
            ->hideOnIndex();
        yield ImageField::new('ogImage', $this->translator->trans('admin.blog_article.og_image', [], 'messages'))
            ->setBasePath('/uploads/images')
            ->setUploadDir('public/uploads/images')
            ->setRequired(false)
            ->hideOnIndex();

        yield AssociationField::new('categories', $this->translator->trans('admin.blog_article.categories', [], 'messages'))
            ->setFormTypeOption('by_reference', false)
            ->autocomplete();
        yield TextareaField::new('content', $this->translator->trans('admin.blog_article.content', [], 'messages'))
            ->hideOnIndex()
            ->setFormTypeOption('attr', ['data-html-editor' => '1']);
        yield TextareaField::new('facebookDraft', $this->translator->trans('admin.blog_article.facebook_draft_field', [], 'messages'))
            ->setHelp($this->translator->trans('admin.blog_article.facebook_draft_field_help', [], 'messages'))
            ->setFormTypeOption('attr', ['rows' => 12])
            ->hideOnIndex();
        yield BooleanField::new('enabled', $this->translator->trans('admin.blog.enabled', [], 'messages'));
        yield DateTimeField::new('publishedAt', $this->translator->trans('admin.blog_article.published_at', [], 'messages'));
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof BlogArticle) {
            $this->ensureUniqueSlug($entityInstance);
            $this->ensureFacebookDraft($entityInstance);
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof BlogArticle) {
            $this->ensureUniqueSlug($entityInstance);
            $this->ensureFacebookDraft($entityInstance);
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function ensureFacebookDraft(BlogArticle $article): void
    {
        if ($article->getFacebookDraft() !== null && $article->getFacebookDraft() !== '') {
            return;
        }

        $article->setFacebookDraft(
            $this->facebookPostFormatter->format($article, $this->resolveBaseUrl($article))
        );
    }

    private function resolveBaseUrl(BlogArticle $article): string
    {
        $domain = $article->getSite()?->getDomain();
        if ($domain !== null && $domain !== '' && !str_contains($domain, 'localhost') && !str_ends_with($domain, '.local')) {
            return 'https://'.$domain;
        }

        return rtrim($this->defaultUri, '/');
    }

    private function ensureUniqueSlug(BlogArticle $article): void
    {
        $article->ensureSlug();
        $site = $article->getSite();
        $locale = $article->getLocale();
        if ($site === null || $locale === null) {
            return;
        }

        $base = $article->getSlug();
        $slug = $base;
        $suffix = 1;
        while ($this->articleRepository->slugExists($site, $locale, $slug, $article->getId())) {
            $slug = $base.'-'.$suffix;
            ++$suffix;
        }
        $article->setSlug($slug);
    }
}
