<?php

declare(strict_types=1);

namespace App\Command;

use App\Database\MigrationRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for database migrations management
 *
 * Runs database migrations to create/update tables structure
 * or rolls them back using --rollback option
 */
class MigrateCommand extends Command
{
    protected static $defaultName = 'app:migrate';

    /**
     * Configure command options and description
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Run database migrations')
            ->addOption('rollback', 'r', InputOption::VALUE_NONE, 'Rollback migrations');
    }

    /**
     * Execute migration process
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int Command status code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runner = new MigrationRunner();

        if ($input->getOption('rollback')) {
            $runner->rollback();
            $output->writeln('Migrations rolled back successfully');
        } else {
            $runner->migrate();
            $output->writeln('Migrations completed successfully');
        }

        return Command::SUCCESS;
    }
}