<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @copyright  Copyright (c) 2011 Vinai Kopp http://netzarbeiter.com, Colin Mollenhour http://colin.mollenhour.com,
 *             Christian Münch http://blog.muench-worms.de
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @author     Vinai Kopp http://netzarbeiter.com
 * @author     Colin Mollenhour http://colin.mollenhour.com
 * @author     Christian Münch http://blog.muench-worms.de
 */

namespace CM\Magerun\CacheBenchmark\Command;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OpsCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('cache:benchmark:ops')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, '(default to default)', 'default')
            ->addOption('client', null, InputOption::VALUE_OPTIONAL, 'The client number', 0)
            ->setDescription('Execute a pre-generated set of operations on the existing cache.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        if ($this->initMagento()) {
            $this->_runOpsBenchmark(
                $output,
                $input->getOption('name'),
                $input->getOption('client'),
                $input->getOption('quiet')
            );
        }
    }

    /**
     * Run the ops benchmark from the specified dataset
     *
     * @param OutputInterface $output
     * @param string          $name
     * @param int             $client
     * @param bool            $quiet
     * @throws \RuntimeException
     */
    protected function _runOpsBenchmark($output, $name, $client, $quiet)
    {
        $testDir = $this->_getTestDir($name);
        $dataFile = "$testDir/ops-client_$client.json";
        if (!file_exists($dataFile)) {
            throw new \RuntimeException("The '$name' test data does not exist. Please run the 'init' command.");
        }
        if (!$quiet) echo "Loading operations...\n";
        $data = json_decode(file_get_contents($dataFile), true);
        if (!$quiet) {
            echo "Executing operations...\n";
            $progressBar = $this->getHelper('progress');
            $progressBar->start($output, count($data) / 100);
        }
        $times = array(
            'read_time' => 0,
            'reads' => 0,
            'write_time' => 0,
            'writes' => 0,
            'clean_time' => 0,
            'cleans' => 0,
        );
        foreach ($data as $i => $op) {
            switch ($op[0]) {
                case 'read':
                    $start = microtime(TRUE);
                    \Mage::app()->loadCache($op[1]);
                    $elapsed = microtime(TRUE) - $start;
                    break;

                case 'write':
                    $string = $this->_getRandomData($op[2]);
                    $start = microtime(TRUE);
                    \Mage::app()->saveCache($string, $op[1], $op[3]);
                    $elapsed = microtime(TRUE) - $start;
                    break;

                case 'clean':
                    $start = microtime(TRUE);
                    \Mage::app()->cleanCache($op[1]);
                    $elapsed = microtime(TRUE) - $start;
                    break;

                default:
                    throw new \RuntimeException('Invalid op: ' . $op[0]);
            }
            $times[$op[0] . '_time'] += $elapsed;
            $times[$op[0] . 's'] += 1;
            if (!$quiet && ($i % 100 == 0)) {
                $progressBar->setCurrent($i / 100);
            }
        }
        if (!$quiet) {
            $progressBar->finish();
        }

        printf("Client %2d", $client);
        foreach(array('read', 'write', 'clean') as $op) {
            printf("|%8.2f", $times[$op . 's'] / $times[$op . '_time']);
        }
        echo "\n";
    }
}