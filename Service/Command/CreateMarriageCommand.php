<?php

namespace CommonGateway\HuwelijksplannerBundle\Command;

use CommonGateway\HuwelijksplannerBundle\Service\CreateMarriageService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to execute the CreateMarriageService.
 */
class CreateMarriageCommand extends Command
{
    protected static $defaultName = 'huwelijksplanner:create:execute';
    private CreateMarriageService $createMarriageService;

    public function __construct(CreateMarriageService $createMarriageService)
    {
        $this->createMarriageService = $createMarriageService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Creates a marriage request object')
            ->setHelp('Creates a marriage request object');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->createMarriageService->setStyle($io);

        if (!$this->createMarriageService->createMarriageHandler([], [])) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
