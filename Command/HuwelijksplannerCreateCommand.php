<?php

namespace CommonGateway\HuwelijksplannerBundle\Command;

use CommonGateway\HuwelijksplannerBundle\Service\HuwelijksplannerCreateService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to execute the HuwelijksplannerCreateService.
 */
class HuwelijksplannerCreateCommand extends Command
{
    protected static $defaultName = 'huwelijksplanner:create:execute';
    private HuwelijksplannerCreateService $huwelijksplannerCreateService;

    public function __construct(HuwelijksplannerCreateService $huwelijksplannerCreateService)
    {
        $this->huwelijksplannerCreateService = $huwelijksplannerCreateService;
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
        $this->huwelijksplannerCreateService->setStyle($io);

        if (!$this->huwelijksplannerCreateService->huwelijksplannerCreateHandler([], [])) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
