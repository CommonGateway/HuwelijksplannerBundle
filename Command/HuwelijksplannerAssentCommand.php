<?php

namespace CommonGateway\HuwelijksplannerBundle\Command;

use CommonGateway\HuwelijksplannerBundle\Service\HuwelijksplannerAssentService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to execute the HuwelijksplannerAssentService.
 */
class HuwelijksplannerAssentCommand extends Command
{
    protected static $defaultName = 'huwelijksplanner:assent:execute';
    private HuwelijksplannerAssentService $huwelijksplannerAssentService;

    public function __construct(HuwelijksplannerAssentService $huwelijksplannerAssentService)
    {
        $this->huwelijksplannerAssentService = $huwelijksplannerAssentService;
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
        $this->huwelijksplannerAssentService->setStyle($io);

        if (!$this->huwelijksplannerAssentService->huwelijksplannerAssentHandler([], [])) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
