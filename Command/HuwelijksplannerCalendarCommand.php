<?php

namespace CommonGateway\HuwelijksplannerBundle\Command;

use CommonGateway\HuwelijksplannerBundle\Service\HuwelijksplannerCalendarService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to execute the HuwelijksplannerCalendarService.
 */
class HuwelijksplannerCalendarCommand extends Command
{
    protected static $defaultName = 'huwelijksplanner:calendar:execute';
    private HuwelijksplannerCalendarService $huwelijksplannerCalendarService;

    public function __construct(HuwelijksplannerCalendarService $huwelijksplannerCalendarService)
    {
        $this->huwelijksplannerCalendarService = $huwelijksplannerCalendarService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('?')
            ->setHelp('?');
        }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->huwelijksplannerCalendarService->setStyle($io);

        if (!$this->huwelijksplannerCalendarService->huwelijksplannerCalendarHandler([], [])) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
