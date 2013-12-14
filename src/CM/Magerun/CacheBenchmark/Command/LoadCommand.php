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

class LoadCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('cache:benchmark:load')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, '(default to default)', 'default')
            ->setDescription('Load an existing dataset.');
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
            $this->_echoCacheConfig($output);
            $this->_loadDataset($output, $input->getOption('name'), $input->getOption('quiet'));
        }
    }

    /**
     * Load the specified dataset
     *
     * @param OutputInterface $output
     * @param string          $name
     * @param bool            $quiet
     * @throws RuntimeException
     */
    protected function _loadDataset($output, $name, $quiet)
    {
        $testDir = $this->_getTestDir($name);
        $dataFile = "$testDir/data.txt";
        if (!file_exists($dataFile)) {
            throw new RuntimeException("The '$name' test data does not exist. Please run the 'init' command.");
        }
        if (!($fp = fopen($dataFile, 'r'))) {
            throw new RuntimeException("Could not open $dataFile");
        }
        $output->writeln("Loading $name test data...");
        $numRecords = rtrim(fgets($fp), "\r\n");
        if (!$quiet) {
            $progressBar = $this->getHelper('progress');
            $progressBar->start($output, $numRecords / 100);
        }
        $i = 0;
        $start = microtime(true);
        $size = 0;
        $_elapsed = 0.;
        while ($line = fgets($fp)) {
            $cache = json_decode($line, true);
            if (!$quiet && $i % 100 == 0) {
                $progressBar->setCurrent($i / 100);
            }
            $i++;
            $size += strlen($cache['data']);
            $_start = microtime(true);
            \Mage::app()->saveCache($cache['data'], $cache['key'], $cache['tags'], $cache['expires']);
            $_elapsed += microtime(true) - $_start;
        }
        fclose($fp);
        $elapsed = microtime(true) - $start;
        if (!$quiet) {
            $progressBar->finish();
        }
        printf("Loaded %d cache records in %.2f seconds (%.4f seconds cache time). Data size is %.1fK\n", $i, $elapsed, $_elapsed, $size / 1024);
    }

    /**
     * Display the configured cache backend(s).
     *
     * @param OutputInterface $output
     */
    protected function _echoCacheConfig($output)
    {
        $backend = (string) \Mage::getConfig()->getNode('global/cache/backend');
        $realBackend = \Mage::app()->getCache()->getBackend();
        $slowBackend = (string) \Mage::getConfig()->getNode('global/cache/slow_backend');

        $backendClass = get_class($realBackend);
        if ($backendClass !== $backend) {
            $backend = "$backend ($backendClass)";
        }
        if ($realBackend instanceof \Zend_Cache_Backend_TwoLevels && '' === $slowBackend) {
            $slowBackend = 'Zend_Cache_Backend_File';
        }

        if ('' === $slowBackend) {
            $output->writeln(sprintf("Cache Backend: %s", $backend));
        } else {
            $output->writeln(sprintf("Cache Backend: %s + %s", $backend, $slowBackend));
        }
    }
}