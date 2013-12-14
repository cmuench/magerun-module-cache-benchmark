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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TagsCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('cache:benchmark:tags')
            ->setDescription('Benchmark getIdsMatchingTags method.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \OutOfRagenException
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        if ($this->initMagento()) {
            $output->writeln("Analyzing current cache contents...");
            $start = microtime(true);

            $nTags = $this->_readTags();
            if (1 > $nTags) {
                throw new \OutOfRangeException('No cache tags found in cache');
            }
            $nEntries = $this->_countCacheRecords();
            if (1 > $nEntries) {
                throw new OutOfRangeException('No cache records found in cache');
            }

            $time = microtime(true) - $start;
            $output->writeln(sprintf("Counted %d cache IDs and %d cache tags in %.4f seconds", $nEntries, $nTags, $time));

            $output->writeln(sprintf("Benchmarking getIdsMatchingTags..."));
            $times = $this->_benchmarkByTag($output, true);

            $this->_echoAverage($times);
        }
    }

    /**
     * Read in list of cache tags. Remove the cache prefix to get the tags
     * specified to Mage_Core_Model_App::saveCache()
     *
     * @return int The number of tags
     */
    protected function _readTags()
    {
        $this->_tags = array();
        $prefix = $this->_getCachePrefix();
        $tags = (array) \Mage::app()->getCache()->getTags();
        $prefixLen = strlen($prefix);
        foreach ($tags as $tag) {
            $tag = substr($tag, $prefixLen);

            // since all records saved through Magento are associated with the
            // MAGE cache tag it is not representative for benchmarking.
            if ('MAGE' === $tag) continue;

            $this->_tags[] = $tag;
        }
        sort($this->_tags);

        return count($this->_tags);
    }

    /**
     * Return the configured cache prefix according to the logic in core/cache.
     *
     * @return string The used cache prefix
     * @see Mage_Core_Model_Cache::__construct()
     */
    protected function _getCachePrefix()
    {
        if (!$this->_cachePrefix) {
            $options = \Mage::getConfig()->getNode('global/cache');
            $prefix = '';
            if ($options) {
                $options = $options->asArray();
                if (isset($options['id_prefix'])) {
                    $prefix = $options['id_prefix'];
                } elseif (isset($options['prefix'])) {
                    $prefix = $options['prefix'];
                }
            }
            if ('' === $prefix) {
                $prefix = substr(md5(\Mage::getConfig()->getOptions()->getEtcDir()), 0, 3) . '_';;
            }
            $this->_cachePrefix = $prefix;
        }
        return $this->_cachePrefix;
    }

    /**
     * Display average values from given tag benchmark times.
     *
     * @param array $times
     */
    protected function _echoAverage(array $times)
    {
        $totalTime = $totalIdCount = 0;
        $numTags = count($times);
        foreach ($times as $time) {
            $totalTime += $time['time'];
            $totalIdCount += $time['count'];
        }
        printf("Average: %.5f seconds (%5.2f ids per tag)\n", $totalTime / $numTags, $totalIdCount / $numTags);
    }

    /**
     * Get the time used for calling getIdsMatchingTags() for every cache tag in
     * the property $_tags.
     * If $verbose is set to true, display detailed statistics for each tag,
     * otherwise display a progress bar.
     *
     * @param OutputInterface $output
     * @param bool $verbose If true output statistics for every cache tag
     * @return array Return an array of timing statistics
     */
    protected function _benchmarkByTag($output, $verbose = false)
    {
        $times = array();

        if (!$verbose) {
            $progressBar = $this->getHelper('progress');
            $progressBar->start($output, count($this->_tags) / 10);
            $counter = 0;
        }

        foreach ($this->_tags as $tag) {
            $start = microtime(true);
            $ids = \Mage::app()->getCache()->getIdsMatchingTags(array($tag));
            $end = microtime(true);
            $times[$tag] = array('time' => $end - $start, 'count' => count($ids));
            if (!$verbose && $counter++ % 10 == 0) {
                $progressBar->update($counter / 10);
            }
        }
        if (!$verbose) {
            $progressBar->finish();
        }
        return $times;
    }

    /**
     * Return the current number of cache records.
     *
     * @return int The current number of cache records
     */
    protected function _countCacheRecords()
    {
        $ids = (array) \Mage::app()->getCache()->getIds();

        return count($ids);
    }
}