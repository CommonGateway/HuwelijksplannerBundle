<?php

namespace CommonGateway\HuwelijksplannerBundle\Command;

use CommonGateway\HuwelijksplannerBundle\Service\UpdateChecklistService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to execute the UpdateChecklistService.
 */
class UpdateChecklistCommand extends Command
{
    protected static $defaultName = 'huwelijksplanner:check:execute';
    private UpdateChecklistService $updateChecklistService;

    public function __construct(UpdateChecklistService $updateChecklistService)
    {
        $this->updateChecklistService = $updateChecklistService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Checks marriage data and updates the associated checklist')
            ->setHelp('Checks marriage data and updates the associated checklist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->updateChecklistService->setStyle($io);

        if (!$this->updateChecklistService->updateChecklistHandler([], [])) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
