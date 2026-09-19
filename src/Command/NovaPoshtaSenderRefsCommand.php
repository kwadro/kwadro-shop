<?php

namespace App\Command;

use App\Service\NovaPoshtaClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:nova-poshta:sender-refs',
    description: 'Fetch Nova Poshta sender REF values for .env configuration',
)]
class NovaPoshtaSenderRefsCommand extends Command
{
    public function __construct(
        private readonly NovaPoshtaClient $novaPoshtaClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('sender', null, InputOption::VALUE_REQUIRED, 'Sender index (1-based)', '1')
            ->addOption('contact', null, InputOption::VALUE_REQUIRED, 'Contact index (1-based)', '1')
            ->addOption('address', null, InputOption::VALUE_REQUIRED, 'Address index (1-based)', '1')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List all senders, contacts and addresses');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->novaPoshtaClient->isConfigured()) {
            $io->error('NOVA_POSHTA_API_KEY is empty. Set it in .env.local and retry.');

            return Command::FAILURE;
        }

        try {
            $senders = $this->novaPoshtaClient->getSenderCounterparties();
        } catch (\Throwable $exception) {
            $io->error('Nova Poshta API request failed: ' . $exception->getMessage());

            return Command::FAILURE;
        }

        if ($senders === []) {
            $io->warning('No senders found. Configure sender profile in new.novaposhta.ua first.');

            return Command::FAILURE;
        }

        if ($input->getOption('list')) {
            $this->renderCatalog($io, $senders);

            return Command::SUCCESS;
        }

        $senderIndex = max(1, (int) $input->getOption('sender')) - 1;
        $contactIndex = max(1, (int) $input->getOption('contact')) - 1;
        $addressIndex = max(1, (int) $input->getOption('address')) - 1;

        if (!isset($senders[$senderIndex])) {
            $io->error(sprintf('Sender #%d not found. Use --list to see available senders.', $senderIndex + 1));

            return Command::FAILURE;
        }

        $sender = $senders[$senderIndex];
        $senderRef = trim((string) ($sender['Ref'] ?? ''));
        if ($senderRef === '') {
            $io->error('Selected sender has empty Ref.');

            return Command::FAILURE;
        }

        try {
            $contacts = $this->novaPoshtaClient->getCounterpartyContactPersons($senderRef);
            $addresses = $this->novaPoshtaClient->getCounterpartyAddresses($senderRef);
        } catch (\Throwable $exception) {
            $io->error('Nova Poshta API request failed: ' . $exception->getMessage());

            return Command::FAILURE;
        }

        if (!isset($contacts[$contactIndex])) {
            $io->error(sprintf('Contact #%d not found for sender #%d. Use --list.', $contactIndex + 1, $senderIndex + 1));

            return Command::FAILURE;
        }

        if (!isset($addresses[$addressIndex])) {
            $io->error(sprintf('Address #%d not found for sender #%d. Use --list.', $addressIndex + 1, $senderIndex + 1));

            return Command::FAILURE;
        }

        $contact = $contacts[$contactIndex];
        $address = $addresses[$addressIndex];

        $contactRef = trim((string) ($contact['Ref'] ?? ''));
        $addressRef = trim((string) ($address['Ref'] ?? ''));
        $cityRef = $this->resolveCityRef($sender, $address);
        $phone = $this->resolvePhone($contact);

        $io->title('Nova Poshta sender configuration');
        $io->definitionList(
            ['Sender' => $this->formatSenderLabel($sender)],
            ['NOVA_POSHTA_SENDER_REF' => $senderRef],
            ['NOVA_POSHTA_SENDER_CONTACT_REF' => $contactRef !== '' ? $contactRef : '—'],
            ['NOVA_POSHTA_SENDER_ADDRESS_REF' => $addressRef !== '' ? $addressRef : '—'],
            ['NOVA_POSHTA_SENDER_CITY_REF' => $cityRef !== '' ? $cityRef : '— (set manually)'],
            ['NOVA_POSHTA_SENDER_PHONE' => $phone !== '' ? $phone : '— (set manually)'],
        );

        if ($contactRef === '' || $addressRef === '') {
            $io->warning('Some REF values are empty. Check sender setup in Nova Poshta business cabinet.');

            return Command::FAILURE;
        }

        $io->section('.env snippet');
        $io->writeln([
            'NOVA_POSHTA_SENDER_REF=' . $senderRef,
            'NOVA_POSHTA_SENDER_CONTACT_REF=' . $contactRef,
            'NOVA_POSHTA_SENDER_ADDRESS_REF=' . $addressRef,
            'NOVA_POSHTA_SENDER_CITY_REF=' . $cityRef,
            'NOVA_POSHTA_SENDER_PHONE=' . $phone,
        ]);

        if ($cityRef === '' || $phone === '') {
            $io->note('Fill missing CityRef or phone manually, then rerun with --list if needed.');

            return Command::FAILURE;
        }

        $io->success('Copy the snippet above into .env.local');

        return Command::SUCCESS;
    }

    /** @param list<array<string, mixed>> $senders */
    private function renderCatalog(SymfonyStyle $io, array $senders): void
    {
        $io->title('Nova Poshta senders');

        foreach ($senders as $index => $sender) {
            $senderRef = trim((string) ($sender['Ref'] ?? ''));
            $io->section(sprintf('#%d %s', $index + 1, $this->formatSenderLabel($sender)));
            $io->writeln('Ref: ' . ($senderRef !== '' ? $senderRef : '—'));

            if ($senderRef === '') {
                continue;
            }

            try {
                $contacts = $this->novaPoshtaClient->getCounterpartyContactPersons($senderRef);
                $addresses = $this->novaPoshtaClient->getCounterpartyAddresses($senderRef);
            } catch (\Throwable $exception) {
                $io->warning('Failed to load details: ' . $exception->getMessage());
                continue;
            }

            if ($contacts !== []) {
                $io->writeln('<info>Contacts</info>');
                foreach ($contacts as $contactIndex => $contact) {
                    $io->writeln(sprintf(
                        '  #%d %s | Ref: %s | Phone: %s',
                        $contactIndex + 1,
                        $this->formatContactLabel($contact),
                        (string) ($contact['Ref'] ?? '—'),
                        $this->resolvePhone($contact) ?: '—',
                    ));
                }
            } else {
                $io->writeln('<comment>No contacts</comment>');
            }

            if ($addresses !== []) {
                $io->writeln('<info>Addresses</info>');
                foreach ($addresses as $addressIndex => $address) {
                    $io->writeln(sprintf(
                        '  #%d %s | Ref: %s | CityRef: %s',
                        $addressIndex + 1,
                        trim((string) ($address['Description'] ?? '—')),
                        (string) ($address['Ref'] ?? '—'),
                        $this->resolveCityRef($sender, $address) ?: '—',
                    ));
                }
            } else {
                $io->writeln('<comment>No addresses</comment>');
            }
        }

        $io->note('Use --sender=1 --contact=1 --address=1 to print .env values (indexes are 1-based).');
    }

    /** @param array<string, mixed> $sender */
    private function formatSenderLabel(array $sender): string
    {
        $parts = array_values(array_filter([
            trim((string) ($sender['Description'] ?? '')),
            trim((string) ($sender['CounterpartyFullName'] ?? '')),
            trim((string) ($sender['CityDescription'] ?? '')),
        ], static fn (string $part): bool => $part !== ''));

        return $parts !== [] ? implode(', ', array_unique($parts)) : 'Sender';
    }

    /** @param array<string, mixed> $contact */
    private function formatContactLabel(array $contact): string
    {
        $parts = array_values(array_filter([
            trim((string) ($contact['LastName'] ?? '')),
            trim((string) ($contact['FirstName'] ?? '')),
            trim((string) ($contact['MiddleName'] ?? '')),
        ], static fn (string $part): bool => $part !== ''));

        return $parts !== [] ? implode(' ', $parts) : trim((string) ($contact['Description'] ?? 'Contact'));
    }

    /** @param array<string, mixed> $sender */
    /** @param array<string, mixed> $address */
    private function resolveCityRef(array $sender, array $address): string
    {
        foreach ([
            trim((string) ($address['CityRef'] ?? '')),
            trim((string) ($address['City'] ?? '')),
            trim((string) ($sender['City'] ?? '')),
        ] as $ref) {
            if ($ref !== '' && $ref !== '00000000-0000-0000-0000-000000000000') {
                return $ref;
            }
        }

        return '';
    }

    /** @param array<string, mixed> $contact */
    private function resolvePhone(array $contact): string
    {
        $raw = trim((string) ($contact['Phones'] ?? $contact['Phone'] ?? ''));
        if ($raw === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '380')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '38' . $digits;
        }

        return '380' . $digits;
    }
}
