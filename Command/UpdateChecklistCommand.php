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
    /**
     * @var string
     */
    protected static $defaultName = 'huwelijksplanner:check:execute';

    /**
     * @var UpdateChecklistService
     */
    private UpdateChecklistService $updateChecklistService;

    /**
     * @param UpdateChecklistService $updateChecklistService
     */
    public function __construct(UpdateChecklistService $updateChecklistService)
    {
        $this->updateChecklistService = $updateChecklistService;
        parent::__construct();

    }//end __construct()


    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Checks marriage data and updates the associated checklist')
            ->setHelp('Checks marriage data and updates the associated checklist');

    }//end configure()


    /**
     * @param InputInterface $input The input
     * @param OutputInterface $output The ouput
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->updateChecklistService->setStyle($io);

        if (!$this->updateChecklistService->updateChecklistHandler([], [])) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;

    }//end execute()


}//end class
