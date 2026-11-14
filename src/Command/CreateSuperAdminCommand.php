<?php

namespace App\Command;

use App\Entity\User;
use App\Service\User\UserGroupAssigner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-super-admin',
    description: 'Create a user in the Super Administrator group',
)]
class CreateSuperAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserGroupAssigner $userGroupAssigner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Super admin email')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Plain password (will prompt if omitted)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $password = $input->getOption('password');

        if (!$password) {
            $password = $io->askHidden('Password');
        }

        if (!$password) {
            $io->error('Password is required.');

            return Command::FAILURE;
        }

        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing instanceof User) {
            $this->userGroupAssigner->assignSuperAdministratorGroup($existing);
            $existing->setPassword($this->passwordHasher->hashPassword($existing, $password));
            $this->entityManager->flush();
            $io->success(sprintf('Updated existing user "%s" as Super Administrator.', $email));

            return Command::SUCCESS;
        }

        $user = new User();
        $user->setEmail($email);
        $this->userGroupAssigner->assignSuperAdministratorGroup($user);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Super Administrator "%s" created.', $email));

        return Command::SUCCESS;
    }
}
