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

class InitCommand extends AbstractCommand
{

    /**
     * Initialize a dataset to files on disk (not loaded into cache)
     */
    protected function configure()
    {
        $this
            ->setName('cache:benchmark:init')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, '(default to default)', 'default')
            ->addOption('keys', null, InputOption::VALUE_OPTIONAL, 'Number of cache keys (default to 10000)', 10000)
            ->addOption('tags', null, InputOption::VALUE_OPTIONAL, 'Number of cache tags (default to 2000)', 2000)
            ->addOption('min-tags', null, InputOption::VALUE_OPTIONAL, 'The min number of tags to use for each record (default 0)', 0)
            ->addOption('max-tags', null, InputOption::VALUE_OPTIONAL, 'The max number of tags to use for each record (default 0)', 15)
            ->addOption('min-rec-size', null, InputOption::VALUE_OPTIONAL, 'The smallest size for a record (default 1)', 1)
            ->addOption('max-rec-size', null, InputOption::VALUE_OPTIONAL, 'The largest size for a record (default 1)', 1024)
            ->addOption('clients', null, InputOption::VALUE_OPTIONAL, 'The number of clients for multi-threaded testing (defaults to 4)', 4)
            ->addOption('ops', null, InputOption::VALUE_OPTIONAL, 'The number of operations per client (defaults to 10000)', 10000)
            ->addOption('write-chance', null, InputOption::VALUE_OPTIONAL, 'The chance-factor that a key will be overwritten (defaults to 1000)', 1000)
            ->addOption('clean-chance', null, InputOption::VALUE_OPTIONAL, 'The chance-factor that a tag will be cleaned (defaults to 5000)', 5000)
            ->addOption('seed', null, InputOption::VALUE_OPTIONAL, 'The random number generator seed (default random)')
            ->setDescription('Inits cache dataset');
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

            $magerunBin = $_SERVER['argv'][0];

            $name = $input->getOption('name');
            $testDir = $this->_getTestDir($name);
            if (is_dir($testDir)) {
                array_map('unlink', glob("$testDir/*.*"));
            }
            if (!is_dir($testDir)) {
                mkdir($testDir);
            }

            // Dump command-line
            file_put_contents("$testDir/cli.txt", 'php ' . implode(' ', $_SERVER['argv']));

            $numKeys = $input->getOption('keys');
            $numTags = $input->getOption('tags');
            $minTags = $input->getOption('min-tags');
            $maxTags = $input->getOption('max-tags');
            $minSize = $input->getOption('min-rec-size');
            $maxSize = $input->getOption('max-rec-size');
            $numClients = $input->getOption('clients');
            if ($input->getOption('seed')) {
                mt_srand((int)$input->getOption('seed'));
            }
            $numOps = $input->getOption('ops');
            $writeFactor = $input->getOption('write-chance');
            $cleanFactor = $input->getOption('clean-chance');

            $tags = array();
            $lengths = array();
            $reads = array();
            $writes = array();
            $expires = array(false, false, false, false, false, 'rand'); // 1/6th of keys expire

            $output->writeln('Generating cache data...');
            $progressBar = $this->getHelper('progress');
            $progressBar->start($output, $numKeys / 100);

            // Generate tags
            $this->_createTagList($numTags);

            $smallestKey = FALSE;
            $largestKey = 0;
            $totalKeyData = 0;

            $leastTags = FALSE;
            $mostTags = 0;
            $totalTags = 0;

            if (!($fp = fopen("$testDir/data.txt", 'w'))) {
                throw new \RuntimeException("Could not open $testDir/data.txt");
            }
            fwrite($fp, "$numKeys\r\n");

            // Generate data
            for ($i = 0; $i < $numKeys; $i++) {
                if ($i % 100 == 0) {
                    $progressBar->setCurrent(($i / 100) + 1);
                }
                $key = md5($i);
                $expireAt = $expires[mt_rand(0, count($expires) - 1)];
                if ($expireAt == 'rand') {
                    $expireAt = mt_rand(10, 14400);
                }
                $data = array(
                    'key' => $key,
                    'data' => $this->_getRandomData($minSize, $maxSize),
                    'tags' => $this->_getRandomTags($minTags, $maxTags),
                    'expires' => $expireAt
                );

                // Store length since data length per key will usually be constant
                $lengths[$key] = strlen($data['data']);
                $tags[$key] = $data['tags'];

                // Some keys are read more frequently
                $popularity = mt_rand(1, 100);
                for ($j = 0; $j < $popularity; $j++) {
                    $reads[] = $key;
                }

                // Some keys are written more frequently
                $volatility = mt_rand(1, 100);
                for ($j = 0; $j < $volatility; $j++) {
                    $writes[] = $key;
                }

                // Stats
                $dataLen = $lengths[$key];
                if ($smallestKey === FALSE || $dataLen < $smallestKey) $smallestKey = $dataLen;
                if ($dataLen > $largestKey) $largestKey = $dataLen;
                $totalKeyData += $dataLen;
                $tagCount = count($data['tags']);
                if ($leastTags === FALSE || $tagCount < $leastTags) $leastTags = $tagCount;
                if ($tagCount > $mostTags) $mostTags = $tagCount;
                $totalTags += $tagCount;
                fwrite($fp, json_encode($data) . "\r\n");
            }
            fclose($fp);
            $progressBar->finish();

            // Dump data
            $averageKey = $totalKeyData / $numKeys;
            $averageTags = $totalTags / $numKeys;
            $description = <<<TEXT
Total Keys: $numKeys
Smallest Key Data: $smallestKey
Largest Key Data: $largestKey
Average Key Data: $averageKey
Total Key Data: $totalKeyData
Total Tags: $numTags
Least Tags/key: $leastTags
Most Tags/key: $mostTags
Average Tags/key: $averageTags
Total Tags/key: $totalTags
TEXT;
            $output->writeln($description);
            file_put_contents("$testDir/description.txt", $description);

            $output->writeln('Generating operations...');
            $progressBar = $this->getHelper('progress');
            $progressBar->start($output, ($numClients * $numOps) / 1000);

            // Create op lists for each client
            for ($i = 0, $k = 0; $i < $numClients; $i++) {
                $ops = array();
                for ($j = 0; $j < $numOps; $j++) {
                    if ($k++ % 1000 === 0) {
                        $progressBar->setCurrent($k / 1000);
                    }

                    // Clean
                    if (mt_rand(0, $cleanFactor) == 0) {
                        $index = mt_rand(0, count($this->_tags) - 1);
                        $tag = $this->_tags[$index];
                        $ops[] = array('clean', $tag);
                    } // Write
                    else if (mt_rand(0, $writeFactor) == 0) {
                        $index = mt_rand(0, count($writes) - 1);
                        $key = $writes[$index];
                        $ops[] = array('write', $key, $lengths[$key], $tags[$key]);
                    } // Read
                    else {
                        $index = mt_rand(0, count($reads) - 1);
                        $key = $reads[$index];
                        $ops[] = array('read', $key);
                    }
                }
                file_put_contents("$testDir/ops-client_$i.json", json_encode($ops));
            }
            $progressBar->finish();

            $quiet = $numClients == 1 ? '' : '--quiet';
            $script = <<<BASH
#!/bin/bash
QUIET='--quiet'; if [ -t 1 ]; then QUIET=''; fi
$magerunBin cache:benchmark:clean
$magerunBin cache:benchmark:load --name '$name' \$QUIET
results=var/cachebench/$name/results.txt
rm -f \$results

clients=0
function runClient() {
  clients=$((clients+1))
  $magerunBin cache:benchmark:ops --name '$name' --client \$1 $quiet \$QUIET >> \$results &
}
echo "Benchmarking $numClients concurrent clients, each with $numOps operations..."
start=$(date '+%s')
BASH;
            $script .= "\n";
            for ($i = 0; $i < $numClients; $i++) {
                $script .= "runClient $i\n";
            }
            $script .= <<<BASH
wait
finish=$(date '+%s')
elapsed=$((finish - start))
echo "\$clients concurrent clients completed in \$elapsed seconds"
echo ""
echo "         |   reads|  writes|  cleans"
echo "------------------------------------"
awk '
BEGIN { FS=OFS="|" }
      { print; for (i=2; i<=NF; ++i) sum[i] += \$i; j=NF }
END   {
        printf "------------------------------------\\n";
        printf "ops/sec  ";
        for (i=2; i <= j; ++i) printf "%s%8.2f", OFS, sum[i];
      }
' \$results
echo ""
BASH;
            file_put_contents("$testDir/run.sh", $script);

            $output->writeln("Completed generation of test data for test '$name'");
            $output->writeln("Run your test like so:\n");
            $output->writeln("  <comment>\$ bash var/cachebench/$name/run.sh</comment>");
        }
    }

    /**
     * Create internal list of cache tags.
     *
     * @param int $nTags The number of tags to create
     */
    protected function _createTagList($nTags)
    {
        $length = strlen('' . $nTags);
        for ($i = 1; $i <= $nTags; $i++) {
            $this->_tags[] = sprintf('TAG_%0' . $length . 'd', $i);
        }
    }

    /**
     * Return a random number of cache tags between the given range.
     *
     * @param int $min
     * @param int $max
     * @return array
     */
    protected function _getRandomTags($min, $max)
    {
        $tags = array();
        $num = mt_rand($min, $max);
        $keys = array_rand($this->_tags, $num);
        foreach ($keys as $i) {
            $tags[] = $this->_tags[$i];
        }
        return $tags;
    }
}