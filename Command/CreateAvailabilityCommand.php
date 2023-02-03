<?php

namespace CommonGateway\HuwelijksplannerBundle\Command;

use CommonGateway\HuwelijksplannerBundle\Service\CreateAvailabilityService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to execute the CreateAvailabilityService.
 */
class CreateAvailabilityCommand extends Command
{
    protected static $defaultName = 'huwelijksplanner:calendar:execute';
    private CreateAvailabilityService $createAvailabilityService;

    public function __construct(CreateAvailabilityService $createAvailabilityService)
    {
        $this->createAvailabilityService = $createAvailabilityService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Creates availability for someone with given date info')
            ->setHelp('Creates availability for someone with given date info');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->createAvailabilityService->setStyle($io);

        if (!$this->createAvailabilityService->createAvailabilityHandler([], [])) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
