<?php

namespace CommonGateway\HuwelijksplannerBundle\Command;

use CommonGateway\HuwelijksplannerBundle\Service\HuwelijksplannerCheckService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to execute the HuwelijksplannerCheckService.
 */
class HuwelijksplannerCheckCommand extends Command
{
    protected static $defaultName = 'huwelijksplanner:check:execute';
    private HuwelijksplannerCheckService $huwelijksplannerCheckService;

    public function __construct(HuwelijksplannerCheckService $huwelijksplannerCheckService)
    {
        $this->huwelijksplannerCheckService = $huwelijksplannerCheckService;
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
        $this->huwelijksplannerCheckService->setStyle($io);

        if (!$this->huwelijksplannerCheckService->huwelijksplannerCheckHandler([], [])) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
