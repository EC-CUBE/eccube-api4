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

namespace Plugin\Api\Command;

use GraphQL\Utils\SchemaPrinter;
use Plugin\Api\GraphQL\Schema;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DumpSchemaCommand extends Command
{
    protected static $defaultName = 'eccube:api:dump-schema';

    /**
     * @var Schema
     */
    private $schema;

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
        $this->addArgument('type', InputArgument::OPTIONAL, 'Type name to dump schema')
            ->setDescription('Dump GraphQL schema.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getArgument('type');
        if ($type) {
            $output->writeln(SchemaPrinter::printType($this->schema->getType($type)));
        } else {
            $output->writeln(SchemaPrinter::doPrint($this->schema));
        }
    }
}
