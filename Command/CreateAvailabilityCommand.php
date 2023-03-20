<?php

namespace CommonGateway\HuwelijksplannerBundle\Command;

use CommonGateway\HuwelijksplannerBundle\Service\CreateAvailabilityService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to execute the CreateAvailabilityService.
 */
class CreateAvailabilityCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'huwelijksplanner:calendar:execute';

    /**
     * @var CreateAvailabilityService
     */
    private CreateAvailabilityService $createAvailabilityService;

    /**
     * @param CreateAvailabilityService $createAvailabilityService
     */
    public function __construct(CreateAvailabilityService $createAvailabilityService)
    {
        $this->createAvailabilityService = $createAvailabilityService;
        parent::__construct();

    }//end __construct()


    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Creates availability for someone with given date info')
            ->setHelp('Creates availability for someone with given date info');

    }//end configure()


    /**
     * @param InputInterface $input The input
     * @param OutputInterface $output The ouput
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->createAvailabilityService->setStyle($io);

        if (!$this->createAvailabilityService->createAvailabilityHandler([], [])) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;

    }//end execute()


}//end class
