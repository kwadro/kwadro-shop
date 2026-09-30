<?php

namespace App\Command;

use App\Repository\BlogArticleRepository;
use App\Service\Blog\BlogFacebookPostFormatter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:blog:facebook-drafts',
    description: 'Generate Facebook plain-text drafts and save them to blog articles in the database',
)]
final class BlogFacebookDraftsCommand extends Command
{
    public function __construct(
        private readonly BlogArticleRepository $articleRepository,
        private readonly BlogFacebookPostFormatter $formatter,
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('slug', null, InputOption::VALUE_REQUIRED, 'Only one article slug')
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'Public site base URL (e.g. https://kvadro.if.ua)')
            ->addOption('extended', null, InputOption::VALUE_NONE, 'Longer plain-text body instead of short teaser')
            ->addOption('stdout', null, InputOption::VALUE_NONE, 'Also print drafts to console')
            ->addOption('files', null, InputOption::VALUE_NONE, 'Also write .txt files under var/export/facebook')
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Output directory for --files', 'var/export/facebook')
            ->addOption('only-empty', null, InputOption::VALUE_NONE, 'Skip articles that already have a facebook draft');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $slug = $input->getOption('slug');
        $baseUrl = $input->getOption('base-url');
        $extended = (bool) $input->getOption('extended');
        $stdout = (bool) $input->getOption('stdout');
        $writeFiles = (bool) $input->getOption('files');
        $onlyEmpty = (bool) $input->getOption('only-empty');

        if ($slug) {
            $articles = $this->articleRepository->findBy(['slug' => $slug], ['id' => 'ASC']);
        } else {
            $articles = $this->articleRepository->findBy([], ['publishedAt' => 'DESC', 'id' => 'DESC']);
        }

        if ($articles === []) {
            $io->warning('No articles found.');

            return Command::SUCCESS;
        }

        $dir = null;
        if ($writeFiles) {
            $dir = (string) $input->getOption('dir');
            if (!str_starts_with($dir, '/')) {
                $dir = $this->projectDir.'/'.ltrim($dir, './');
            }
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                $io->error(sprintf('Cannot create directory: %s', $dir));

                return Command::FAILURE;
            }
        }

        $saved = 0;
        $skipped = 0;
        foreach ($articles as $article) {
            if ($onlyEmpty && $article->getFacebookDraft() !== null && $article->getFacebookDraft() !== '') {
                ++$skipped;
                $io->writeln(' · skip '.$article->getSlug().' (already has draft)');
                continue;
            }

            $text = $extended
                ? $this->formatter->formatExtended($article, $baseUrl)
                : $this->formatter->format($article, $baseUrl);

            $article->setFacebookDraft($text);
            $this->em->persist($article);
            ++$saved;

            $io->writeln(' ✓ '.$article->getSlug());

            if ($stdout) {
                $io->section($article->getSlug());
                $io->writeln($text);
            }

            if ($writeFiles && $dir !== null) {
                file_put_contents($dir.'/'.$article->getSlug().'.txt', $text);
            }
        }

        $this->em->flush();

        $io->success(sprintf('Saved %d Facebook draft(s) to database%s.', $saved, $skipped > 0 ? sprintf(', skipped %d', $skipped) : ''));
        $io->note('Open Blog → article → «Facebook пост» in admin to view and copy.');

        return Command::SUCCESS;
    }
}
