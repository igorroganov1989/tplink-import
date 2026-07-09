<?php

declare(strict_types=1);

namespace Yd\TplinkImport\Infrastructure\Command;

use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yd\TplinkImport\Service\Import\CatalogImporter;

#[AsCommand(
    name: 'yd.tplink.import:run',
    description: 'Import TP-Link Wi-Fi routers catalog from the official website',
)]
final class ImportCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!Loader::includeModule('yd.tplink.import')) {
            $output->writeln('<error>Module yd.tplink.import is not installed.</error>');

            return Command::FAILURE;
        }

        if (!Loader::includeModule('iblock')) {
            $output->writeln('<error>Module iblock is not available.</error>');

            return Command::FAILURE;
        }

        try {
            /** @var CatalogImporter $importer */
            $importer = ServiceLocator::getInstance()->get('yd.tplink.import.catalog.importer');
            $result = $importer->run();

            $output->writeln('<info>Import completed.</info>');
            foreach ($result['counters'] as $name => $count) {
                $output->writeln(sprintf('  %s: %d', $name, $count));
            }
            $output->writeln('Log: ' . $result['log_path']);

            return ($result['counters']['errors'] ?? 0) > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
