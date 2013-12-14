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

class AnalyzeCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('cache:benchmark:analyse')
            ->setDescription('Analyze the current cache contents.');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        if ($this->initMagento()) {

            $backend = \Mage::app()->getCache()->getBackend();
            /* @var $backend \Zend_Cache_Backend_ExtendedInterface */

            $totalSize = 0;
            $totalKeyTags = 0;
            $totalKeysTag = 0;
            $sizeBuckets = array();
            $tagsBuckets = array();
            $keysBuckets = array();
            $ids = $backend->getIds();
            $totalIds = count($ids);
            foreach ($ids as $id) {
                $data = $backend->load($id);
                $meta = $backend->getMetadatas($id);
                $size = strlen($data);
                $totalSize += $size;
                $sizeBucket = (int)floor($size / 2048);
                $sizeBuckets[$sizeBucket]++;
                $totalKeyTags += count($meta['tags']);
                $tagsBucket = (int)floor(count($meta['tags']) / 1);
                $tagsBuckets[$tagsBucket]++;
            }
            $ids = NULL;
            $avgSize = round($totalSize / $totalIds / 1024, 2);
            $totalSize = round($totalSize / (1024 * 1024), 2);
            $avgTags = $totalKeyTags / $totalIds;
            $tags = $backend->getTags();
            $totalTags = count($tags);
            foreach ($tags as $tag) {
                $tagCount = count($backend->getIdsMatchingAnyTags(array($tag)));
                $keysBucket = (int)floor($tagCount / 1);
                $keysBuckets[$keysBucket]++;
                $totalKeysTag += $tagCount;
            }
            $tags = NULL;
            $avgKeys = round($totalKeysTag / $totalTags, 2);
            $output->writeln(
            "Total Ids\t$totalIds
Total Size\t{$totalSize} Mb
Total Tags\t$totalTags
Average Size\t$avgSize Kb
Average Tags/Key\t$avgTags
Average Keys/Tag\t$avgKeys
");
            ksort($sizeBuckets);
            $output->writeln("Under Kb\tCount");
            foreach ($sizeBuckets as $size => $count) {
                $size *= 2;
                $output->writeln("{$size}Kb\t$count");
            }
            ksort($tagsBuckets);
            $output->writeln("Tags/Key\tCount");
            foreach ($tagsBuckets as $tags => $count) {
                $tags *= 1;
                $output->writeln("{$tags} tags\t$count");
            }
            $output->writeln("Keys/Tag\tCount");
            ksort($keysBuckets);
            foreach ($keysBuckets as $keys => $count) {
                $keys *= 1;
                $output->writeln("{$keys} keys\t$count");
            }
        }
    }
}