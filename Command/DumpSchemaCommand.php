<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\Api44\Command;

use GraphQL\Utils\SchemaPrinter;
use Plugin\Api44\GraphQL\Schema;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'eccube:api:dump-schema', description: 'Dump GraphQL schema.')]
class DumpSchemaCommand extends Command
{
    /**
     * @var Schema
     */
    private Schema $schema;

    /**
     * DumpSchemaCommand constructor.
     */
    public function __construct(Schema $schema)
    {
        parent::__construct();
        $this->schema = $schema;
    }

    protected function configure()
    {
        $this->addArgument('type', InputArgument::OPTIONAL, 'Type name to dump schema');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getArgument('type');
        if ($type) {
            $output->writeln(SchemaPrinter::printType($this->schema->getType($type)));
        } else {
            $output->writeln(SchemaPrinter::doPrint($this->schema));
        }

        return Command::SUCCESS;
    }
}
